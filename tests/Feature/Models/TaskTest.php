<?php

namespace Tests\Feature\Models;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_task_done_records_when_it_was_completed(): void
    {
        $this->freezeSecond();
        $task = Task::factory()->create();

        $task->update(['status' => TaskStatus::Done]);

        $this->assertTrue($task->fresh()->completed_at->equalTo(now()));
    }

    public function test_reopening_a_done_task_clears_the_completion_time(): void
    {
        $task = Task::factory()->done()->create();

        $task->update(['status' => TaskStatus::InProgress]);

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_editing_other_fields_keeps_the_original_completion_time(): void
    {
        $task = Task::factory()->done()->create();
        $completedAt = $task->completed_at;

        $this->travel(2)->days();
        $task->update(['title' => 'Renamed']);

        $this->assertTrue($task->fresh()->completed_at->equalTo($completedAt));
    }

    public function test_a_past_due_open_task_is_overdue(): void
    {
        $this->assertTrue(Task::factory()->overdue()->make()->isOverdue());
    }

    public function test_a_task_due_today_is_not_overdue(): void
    {
        $this->assertFalse(Task::factory()->dueOn(today()->toDateString())->make()->isOverdue());
    }

    public function test_a_done_task_is_never_overdue(): void
    {
        $this->assertFalse(Task::factory()->overdue()->done()->make()->isOverdue());
    }
}
