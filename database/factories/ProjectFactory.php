<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->catchPhrase(),
            'description' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * Add the given users as members alongside the owner.
     */
    public function withMembers(User ...$users): static
    {
        return $this->afterCreating(function (Project $project) use ($users): void {
            $project->members()->attach(collect($users)->pluck('id'));
        });
    }
}
