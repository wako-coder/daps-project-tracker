<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->catchPhrase(),
            'description' => fake()->sentence(),
            'budget_santim' => fake()->numberBetween(1_000_00, 5_000_000_00),
        ];
    }
}
