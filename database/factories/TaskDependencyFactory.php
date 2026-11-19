<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskDependency>
 */
class TaskDependencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'depends_on_task_id' => Task::factory(),
            'type' => 'finish_to_start',
        ];
    }
}
