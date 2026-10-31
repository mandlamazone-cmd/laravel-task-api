<?php

namespace Tests\Feature\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_member_can_view_and_update_a_task(): void
    {
        $member = User::factory()->create();
        $task = Task::factory()->for(Project::factory()->withMembers($member))->create();
        $policy = new TaskPolicy;

        $this->assertTrue($policy->view($member, $task)->allowed());
        $this->assertTrue($policy->update($member, $task)->allowed());
    }

    public function test_the_creator_and_the_project_owner_can_delete_a_task(): void
    {
        $creator = User::factory()->create();
        $project = Project::factory()->withMembers($creator)->create();
        $task = Task::factory()->for($project)->create(['creator_id' => $creator->id]);
        $policy = new TaskPolicy;

        $this->assertTrue($policy->delete($creator, $task)->allowed());
        $this->assertTrue($policy->delete($project->owner, $task)->allowed());
    }

    public function test_other_members_are_forbidden_from_deleting_a_task(): void
    {
        $member = User::factory()->create();
        $task = Task::factory()->for(Project::factory()->withMembers($member))->create();

        $response = (new TaskPolicy)->delete($member, $task);

        $this->assertTrue($response->denied());
        $this->assertNull($response->status());
    }

    public function test_outsiders_are_denied_as_not_found_for_every_ability(): void
    {
        $outsider = User::factory()->create();
        $task = Task::factory()->create();
        $policy = new TaskPolicy;

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertSame(404, $policy->{$ability}($outsider, $task)->status(), $ability);
        }
    }
}
