<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'settings' => null,
        ];
    }

    /**
     * Indicate that the workspace has settings.
     */
    public function withSettings(array $settings = []): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => $settings ?: [
                'timezone' => 'America/Sao_Paulo',
                'date_format' => 'Y-m-d',
                'default_view' => 'kanban',
            ],
        ]);
    }
}
