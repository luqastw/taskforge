<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectColumn>
 */
class ProjectColumnFactory extends Factory
{
    protected $model = ProjectColumn::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
            'order' => fake()->numberBetween(1, 10),
            'task_limit' => null,
        ];
    }

    public function withTaskLimit(int $limit = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'task_limit' => $limit,
        ]);
    }
}
