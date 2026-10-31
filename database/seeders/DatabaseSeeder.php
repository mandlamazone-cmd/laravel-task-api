<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a demo account with two projects and a spread of tasks.
     */
    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);

        $teammates = User::factory()->count(3)->create();

        Project::factory()
            ->for($demo, 'owner')
            ->withMembers(...$teammates)
            ->has(Task::factory()->count(8)->sequence(
                fn ($sequence) => ['assignee_id' => [$demo->id, ...$teammates->pluck('id')][$sequence->index % 4]],
            ))
            ->has(Task::factory()->count(3)->overdue()->priority(TaskPriority::High))
            ->has(Task::factory()->count(4)->done())
            ->create(['name' => 'Mobile app launch']);

        Project::factory()
            ->for($teammates->first(), 'owner')
            ->withMembers($demo)
            ->has(Task::factory()->count(5)->inProgress())
            ->create(['name' => 'Marketing site refresh']);
    }
}
