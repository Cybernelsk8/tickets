<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'parent_id' => null,
            'manager_id' => null,
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the area is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the manager responsible for the area.
     */
    public function managedBy(User $manager): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_id' => $manager->id,
            'slug' => Str::slug($attributes['name']).'-'.$manager->id,
        ]);
    }
}
