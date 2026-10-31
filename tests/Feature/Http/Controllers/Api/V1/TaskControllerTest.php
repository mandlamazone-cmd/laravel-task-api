<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_no_token_is_provided(): void
    {
        $project = Project::factory()->create();

        $this->getJson("/api/v1/projects/{$project->id}/tasks")->assertUnauthorized();
    }

    public function test_index_returns_404_for_a_project_the_user_is_not_in(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Task::factory()->for($project)->create();

        $this->getJson("/api/v1/projects/{$project->id}/tasks")->assertNotFound();
    }

    public function test_index_lists_the_project_tasks_newest_first_with_pagination(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $tasks = Task::factory()->for($project)->count(3)->create();
        Task::factory()->create();

        $this->getJson("/api/v1/projects/{$project->id}/tasks?per_page=2")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$tasks[2]->id, $tasks[1]->id])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_filters_by_status_and_priority(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $match = Task::factory()->for($project)->inProgress()->priority(TaskPriority::High)->create();
        Task::factory()->for($project)->inProgress()->priority(TaskPriority::Low)->create();
        Task::factory()->for($project)->priority(TaskPriority::High)->create();

        $this->getJson("/api/v1/projects/{$project->id}/tasks?status=in_progress&priority=high")
            ->assertJsonPath('data.*.id', [$match->id]);
    }

    public function test_index_filters_by_assignee(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $colleague = User::factory()->create();
        $project->members()->attach($colleague);
        $mine = Task::factory()->for($project)->assignedTo($user)->create();
        $theirs = Task::factory()->for($project)->assignedTo($colleague)->create();
        $unassigned = Task::factory()->for($project)->create();

        $url = "/api/v1/projects/{$project->id}/tasks?assignee=";
        $this->getJson($url.'me')->assertJsonPath('data.*.id', [$mine->id]);
        $this->getJson($url.'none')->assertJsonPath('data.*.id', [$unassigned->id]);
        $this->getJson($url.$colleague->id)->assertJsonPath('data.*.id', [$theirs->id]);
    }

    public function test_index_filters_overdue_tasks(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $overdue = Task::factory()->for($project)->overdue()->create();
        Task::factory()->for($project)->overdue()->done()->create();
        Task::factory()->for($project)->dueOn(today()->toDateString())->create();

        $this->getJson("/api/v1/projects/{$project->id}/tasks?overdue=1")
            ->assertJsonPath('data.*.id', [$overdue->id])
            ->assertJsonPath('data.0.is_overdue', true);
    }

    public function test_index_searches_titles_and_treats_wildcards_literally(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $match = Task::factory()->for($project)->create(['title' => 'Fix 100% CPU on login']);
        Task::factory()->for($project)->create(['title' => 'Fix 1000 CPU warnings']);

        $this->getJson("/api/v1/projects/{$project->id}/tasks?search=".urlencode('100%'))
            ->assertJsonPath('data.*.id', [$match->id]);
    }

    public function test_index_sorts_by_priority_weight_rather_than_alphabetically(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $medium = Task::factory()->for($project)->priority(TaskPriority::Medium)->create();
        $high = Task::factory()->for($project)->priority(TaskPriority::High)->create();
        $low = Task::factory()->for($project)->priority(TaskPriority::Low)->create();

        $this->getJson("/api/v1/projects/{$project->id}/tasks?sort=-priority")
            ->assertJsonPath('data.*.id', [$high->id, $medium->id, $low->id]);
    }

    public function test_index_sorts_by_due_date_with_undated_tasks_last(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $undated = Task::factory()->for($project)->create(['due_on' => null]);
        $later = Task::factory()->for($project)->dueOn('2030-05-01')->create();
        $sooner = Task::factory()->for($project)->dueOn('2030-01-01')->create();

        $this->getJson("/api/v1/projects/{$project->id}/tasks?sort=due_on")
            ->assertJsonPath('data.*.id', [$sooner->id, $later->id, $undated->id]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidListQueries(): array
    {
        return [
            'unknown status' => ['status=blocked', 'status', 'The selected status is invalid.'],
            'sql in sort column' => ['sort=id;drop table tasks', 'sort', 'The sort must be one of: created_at, due_on, priority, title (prefix with "-" for descending).'],
            'unsortable column' => ['sort=creator_id', 'sort', 'The sort must be one of: created_at, due_on, priority, title (prefix with "-" for descending).'],
            'malformed assignee' => ['assignee=everyone', 'assignee', 'The assignee must be "me", "none" or a user id.'],
            'page size too large' => ['per_page=500', 'per_page', 'The per page field must be between 1 and 100.'],
        ];
    }

    #[DataProvider('invalidListQueries')]
    public function test_index_rejects_invalid_query_parameters_with_422(string $query, string $field, string $message): void
    {
        [$user, $project] = $this->signInAsOwner();

        $this->getJson("/api/v1/projects/{$project->id}/tasks?{$query}")
            ->assertJsonValidationErrors([$field => $message]);
    }

    public function test_store_creates_a_task_with_the_signed_in_user_as_creator(): void
    {
        [$user, $project] = $this->signInAsMember();

        $response = $this->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'Write onboarding guide',
            'priority' => 'high',
            'due_on' => '2030-03-15',
            'assignee_id' => $user->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Write onboarding guide')
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.due_on', '2030-03-15')
            ->assertJsonPath('data.creator.id', $user->id)
            ->assertJsonPath('data.assignee.id', $user->id);

        $task = Task::findOrFail($response->json('data.id'));
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame($user->id, $task->creator_id);
    }

    public function test_store_rejects_an_empty_payload_with_422(): void
    {
        [$user, $project] = $this->signInAsOwner();

        $this->postJson("/api/v1/projects/{$project->id}/tasks")
            ->assertJsonValidationErrors(['title' => 'The title field is required.']);
    }

    public function test_store_rejects_an_assignee_from_outside_the_project(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $outsider = User::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Task', 'assignee_id' => $outsider->id])
            ->assertJsonValidationErrors(['assignee_id' => 'Tasks can only be assigned to members of this project.']);
    }

    public function test_store_rejects_a_badly_formatted_due_date(): void
    {
        [$user, $project] = $this->signInAsOwner();

        $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Task', 'due_on' => '15/03/2030'])
            ->assertJsonValidationErrors(['due_on' => 'The due on field must match the format Y-m-d.']);
    }

    public function test_store_ignores_a_project_id_or_creator_id_in_the_payload(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $otherProject = Project::factory()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'Task',
            'project_id' => $otherProject->id,
            'creator_id' => $otherProject->owner_id,
        ]);

        $task = Task::findOrFail($response->json('data.id'));
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame($user->id, $task->creator_id);
    }

    public function test_store_returns_404_for_a_project_the_user_is_not_in(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Sneaky'])->assertNotFound();

        $this->assertSame(0, $project->tasks()->count());
    }

    public function test_show_returns_404_when_the_task_belongs_to_another_project(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $foreignTask = Task::factory()->create();

        $this->getJson("/api/v1/projects/{$project->id}/tasks/{$foreignTask->id}")->assertNotFound();
    }

    public function test_update_lets_a_member_complete_a_task(): void
    {
        [$user, $project] = $this->signInAsMember();
        $task = Task::factory()->for($project)->create();

        $this->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_update_can_unassign_a_task(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $task = Task::factory()->for($project)->assignedTo($user)->create();

        $this->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['assignee_id' => null])
            ->assertOk()
            ->assertJsonPath('data.assignee', null);

        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_update_rejects_a_null_status(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $task = Task::factory()->for($project)->create();

        $this->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['status' => null])
            ->assertJsonValidationErrors(['status' => 'The status field is required.']);
    }

    public function test_update_returns_404_through_another_project_url(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $foreignTask = Task::factory()->create(['title' => 'Original']);

        $this->patchJson("/api/v1/projects/{$project->id}/tasks/{$foreignTask->id}", ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Original', $foreignTask->fresh()->title);
    }

    public function test_destroy_lets_the_project_owner_delete_any_task(): void
    {
        [$user, $project] = $this->signInAsOwner();
        $task = Task::factory()->for($project)->create(['creator_id' => User::factory()->create()->id]);

        $this->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")->assertNoContent();

        $this->assertModelMissing($task);
    }

    public function test_destroy_returns_403_for_a_member_who_did_not_create_the_task(): void
    {
        [$user, $project] = $this->signInAsMember();
        $task = Task::factory()->for($project)->create();

        $this->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the task creator or the project owner can delete this task.');

        $this->assertModelExists($task);
    }

    /**
     * @return array{User, Project}
     */
    private function signInAsOwner(): array
    {
        $user = Sanctum::actingAs(User::factory()->create());

        return [$user, Project::factory()->for($user, 'owner')->create()];
    }

    /**
     * @return array{User, Project}
     */
    private function signInAsMember(): array
    {
        $user = Sanctum::actingAs(User::factory()->create());

        return [$user, Project::factory()->withMembers($user)->create()];
    }
}
