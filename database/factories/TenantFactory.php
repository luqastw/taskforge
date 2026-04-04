<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'settings' => [
                'timezone' => 'UTC',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i:s',
            ],
        ];
    }

    /**
     * Indicate that the tenant has custom settings.
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], $settings),
        ]);
    }
}
