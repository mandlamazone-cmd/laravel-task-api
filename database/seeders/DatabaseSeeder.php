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

        $teammates = User::factory()->count(3)->sequence(
            ['name' => 'Amara Okafor', 'email' => 'amara@example.com'],
            ['name' => 'Lucas Meyer', 'email' => 'lucas@example.com'],
            ['name' => 'Priya Shah', 'email' => 'priya@example.com'],
        )->create();

        Project::factory()
            ->for($demo, 'owner')
            ->withMembers(...$teammates)
            ->has(Task::factory()->count(8)->sequence(
                fn ($sequence) => ['assignee_id' => [$demo->id, ...$teammates->pluck('id')][$sequence->index % 4]],
            ))
            ->has(Task::factory()->count(3)->overdue()->priority(TaskPriority::High))
            ->has(Task::factory()->count(4)->done())
            ->create([
                'name' => 'Mobile app launch',
                'description' => 'Everything needed to ship version 1.0 to the App Store and Google Play.',
            ]);

        Project::factory()
            ->for($teammates->first(), 'owner')
            ->withMembers($demo)
            ->has(Task::factory()->count(5)->inProgress())
            ->create([
                'name' => 'Marketing site refresh',
                'description' => 'New landing pages, faster load times and an updated pricing page.',
            ]);
    }
}
