<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson('/api/v1/projects')->assertUnauthorized();
    }

    public function test_index_lists_only_projects_the_user_belongs_to(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $owned = Project::factory()->for($user, 'owner')->create();
        $joined = Project::factory()->withMembers($user)->create();
        Project::factory()->create();

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $joined->id)
            ->assertJsonPath('data.1.id', $owned->id);
    }

    public function test_index_counts_members_and_open_tasks(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->for($user, 'owner')->withMembers(User::factory()->create())->create();
        Task::factory()->for($project)->count(2)->create();
        Task::factory()->for($project)->done()->create();

        $this->getJson('/api/v1/projects')
            ->assertJsonPath('data.0.members_count', 2)
            ->assertJsonPath('data.0.open_tasks_count', 2);
    }

    public function test_store_creates_project_owned_by_user_and_returns_201(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/projects', ['name' => 'Website relaunch', 'description' => 'Q4 goals']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Website relaunch')
            ->assertJsonPath('data.owner.id', $user->id)
            ->assertJsonPath('data.members.0.id', $user->id);

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertTrue($project->isOwnedBy($user));
        $this->assertTrue($project->hasMember($user));
    }

    public function test_store_rejects_a_missing_name_with_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/projects', ['description' => 'No name'])
            ->assertJsonValidationErrors(['name' => 'The name field is required.']);
    }

    public function test_store_ignores_an_owner_id_in_the_payload(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $someoneElse = User::factory()->create();

        $response = $this->postJson('/api/v1/projects', ['name' => 'Mine', 'owner_id' => $someoneElse->id]);

        $this->assertSame($user->id, Project::find($response->json('data.id'))->owner_id);
    }

    public function test_show_returns_project_to_a_member(): void
    {
        $member = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->withMembers($member)->create();

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonCount(2, 'data.members');
    }

    public function test_show_returns_404_for_a_project_the_user_is_not_in(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->getJson("/api/v1/projects/{$project->id}")->assertNotFound();
    }

    public function test_update_changes_the_project_for_its_owner(): void
    {
        $owner = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->patchJson("/api/v1/projects/{$project->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertSame('Renamed', $project->fresh()->name);
    }

    public function test_update_returns_403_for_a_member_who_is_not_the_owner(): void
    {
        $member = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->withMembers($member)->create(['name' => 'Original']);

        $this->patchJson("/api/v1/projects/{$project->id}", ['name' => 'Hijacked'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the project owner can do this.');

        $this->assertSame('Original', $project->fresh()->name);
    }

    public function test_destroy_deletes_the_project_and_its_tasks(): void
    {
        $owner = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->for($owner, 'owner')->create();
        $task = Task::factory()->for($project)->create();

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertNoContent();

        $this->assertModelMissing($project);
        $this->assertModelMissing($task);
    }

    public function test_destroy_returns_403_for_a_member_who_is_not_the_owner(): void
    {
        $member = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->withMembers($member)->create();

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertForbidden();

        $this->assertModelExists($project);
    }
}
