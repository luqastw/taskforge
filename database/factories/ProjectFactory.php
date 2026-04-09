<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
            'deadline' => fake()->optional()->dateTimeBetween('now', '+1 year'),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    public function onHold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'on_hold',
        ]);
    }
}
