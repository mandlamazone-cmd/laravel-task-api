<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_members_to_a_member(): void
    {
        $member = Sanctum::actingAs(User::factory()->create(['name' => 'Zed']));
        $project = Project::factory()->for(User::factory()->create(['name' => 'Amy']), 'owner')->withMembers($member)->create();

        $this->getJson("/api/v1/projects/{$project->id}/members")
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Amy', 'Zed']);
    }

    public function test_store_adds_an_existing_user_by_email_and_returns_201(): void
    {
        $owner = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->for($owner, 'owner')->create();
        $newcomer = User::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/members", ['email' => $newcomer->email])
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $this->assertTrue($project->hasMember($newcomer));
    }

    public function test_store_rejects_an_unknown_email(): void
    {
        $owner = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->postJson("/api/v1/projects/{$project->id}/members", ['email' => 'nobody@example.com'])
            ->assertJsonValidationErrors(['email' => 'No account uses this email address.']);
    }

    public function test_store_rejects_a_user_who_is_already_a_member(): void
    {
        $owner = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->postJson("/api/v1/projects/{$project->id}/members", ['email' => $owner->email])
            ->assertJsonValidationErrors(['email' => 'This user is already a member of the project.']);
    }

    public function test_store_returns_403_for_a_member_who_is_not_the_owner(): void
    {
        $member = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->withMembers($member)->create();
        $newcomer = User::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/members", ['email' => $newcomer->email])->assertForbidden();

        $this->assertFalse($project->hasMember($newcomer));
    }

    public function test_store_returns_404_for_an_outsider(): void
    {
        $outsider = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/members", ['email' => $outsider->email])->assertNotFound();

        $this->assertFalse($project->hasMember($outsider));
    }

    public function test_destroy_removes_member_and_unassigns_their_tasks_in_that_project_only(): void
    {
        $owner = Sanctum::actingAs(User::factory()->create());
        $member = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->withMembers($member)->create();
        $otherProject = Project::factory()->withMembers($member)->create();
        $task = Task::factory()->for($project)->assignedTo($member)->create();
        $otherTask = Task::factory()->for($otherProject)->assignedTo($member)->create();

        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$member->id}")->assertNoContent();

        $this->assertFalse($project->hasMember($member));
        $this->assertNull($task->fresh()->assignee_id);
        $this->assertSame($member->id, $otherTask->fresh()->assignee_id);
    }

    public function test_destroy_refuses_to_remove_the_owner(): void
    {
        $owner = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$owner->id}")
            ->assertJsonValidationErrors(['user' => 'The project owner cannot be removed.']);

        $this->assertTrue($project->hasMember($owner));
    }

    public function test_destroy_returns_404_for_a_user_who_is_not_a_member(): void
    {
        $owner = Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->deleteJson("/api/v1/projects/{$project->id}/members/".User::factory()->create()->id)->assertNotFound();
    }

    public function test_destroy_returns_403_for_a_member_who_is_not_the_owner(): void
    {
        $member = Sanctum::actingAs(User::factory()->create());
        $colleague = User::factory()->create();
        $project = Project::factory()->withMembers($member, $colleague)->create();

        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$colleague->id}")->assertForbidden();

        $this->assertTrue($project->hasMember($colleague));
    }
}
