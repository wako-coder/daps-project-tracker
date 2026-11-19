<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->unique()->sentence(3),
            'estimated_cost_santim' => fake()->numberBetween(100_00, 500_000_00),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
        ];
    }
}
