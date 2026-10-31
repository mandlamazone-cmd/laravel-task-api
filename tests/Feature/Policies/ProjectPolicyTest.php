<?php

namespace Tests\Feature\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, bool, bool, bool}>
     */
    public static function abilityMatrix(): array
    {
        // ability => [ability, owner allowed, member allowed, member gets 403 (not 404)]
        return [
            'view' => ['view', true, true, false],
            'update' => ['update', true, false, true],
            'delete' => ['delete', true, false, true],
            'manage members' => ['manageMembers', true, false, true],
        ];
    }

    #[DataProvider('abilityMatrix')]
    public function test_owner_and_member_permissions_match_the_matrix(string $ability, bool $ownerAllowed, bool $memberAllowed): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->withMembers($member)->create();
        $policy = new ProjectPolicy;

        $this->assertSame($ownerAllowed, $policy->{$ability}($project->owner, $project)->allowed());
        $this->assertSame($memberAllowed, $policy->{$ability}($member, $project)->allowed());
    }

    #[DataProvider('abilityMatrix')]
    public function test_outsiders_are_denied_as_not_found_for_every_ability(string $ability): void
    {
        $project = Project::factory()->create();

        $response = (new ProjectPolicy)->{$ability}(User::factory()->create(), $project);

        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }
}
