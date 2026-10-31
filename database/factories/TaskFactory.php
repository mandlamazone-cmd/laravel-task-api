<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'creator_id' => fn (array $attributes) => Project::find($attributes['project_id'])->owner_id,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'due_on' => fake()->optional()->dateTimeBetween('now', '+1 month'),
        ];
    }

    public function done(): static
    {
        return $this->state(['status' => TaskStatus::Done]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => TaskStatus::InProgress]);
    }

    public function priority(TaskPriority $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(['due_on' => $date]);
    }

    public function overdue(): static
    {
        return $this->state(['due_on' => today()->subDays(3)]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(['assignee_id' => $user->id]);
    }
}
