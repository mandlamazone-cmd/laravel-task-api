<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token_with_201(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'device_name' => 'iPhone',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $user = User::firstWhere('email', 'ada@example.com');
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('iPhone', $user->tokens()->sole()->name);
    }

    public function test_register_rejects_empty_payload_with_422(): void
    {
        $this->postJson('/api/v1/auth/register')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name']);
    }

    public function test_register_rejects_an_email_that_is_already_taken(): void
    {
        $existing = User::factory()->create();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone',
            'email' => $existing->email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'device_name' => 'iPhone',
        ])->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function test_register_rejects_a_mismatched_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'different-password',
            'device_name' => 'iPhone',
        ])->assertJsonValidationErrors(['password' => 'The password field confirmation does not match.']);
    }

    public function test_register_ignores_attributes_the_client_must_not_set(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'device_name' => 'iPhone',
            'email_verified_at' => now()->toIso8601String(),
        ])->assertCreated();

        $this->assertNull(User::firstWhere('email', 'someone@example.com')->email_verified_at);
    }

    public function test_login_returns_a_token_for_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Pixel',
        ]);

        $response->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertNotEmpty($response->json('token'));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_login_rejects_a_wrong_password_with_422(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'Pixel',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_returns_429_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();
        $payload = ['email' => $user->email, 'password' => 'wrong-password', 'device_name' => 'Pixel'];

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', $payload)->assertTooManyRequests();
    }

    public function test_me_returns_the_signed_in_user(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);
    }

    public function test_me_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('phone')->plainTextToken;
        $user->createToken('laptop');

        $this->withToken($current)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(['laptop'], $user->tokens()->pluck('name')->all());
    }
}
