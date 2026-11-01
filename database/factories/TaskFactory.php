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
            'title' => fake()->randomElement([
                'Write release notes', 'Fix login timeout on slow networks', 'Design empty states',
                'Review pull request backlog', 'Add CSV export', 'Set up error monitoring',
                'Update onboarding emails', 'Benchmark search endpoint', 'Draft Q4 roadmap',
                'Migrate images to object storage', 'Write API rate limit docs', 'Plan user interviews',
            ]),
            'description' => fake()->optional()->randomElement([
                'Agree the scope with the team first, then split it into smaller tasks if it grows.',
                'Check the analytics dashboard before and after the change so we can compare.',
                'Pair with whoever owns this area; they know the edge cases.',
                'Keep the old behaviour behind a flag until the release is confirmed.',
            ]),
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
