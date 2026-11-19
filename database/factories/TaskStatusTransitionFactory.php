<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskStatusTransition;
use App\Models\User;
use App\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskStatusTransition>
 */
class TaskStatusTransitionFactory extends Factory
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
            'from_status' => null,
            'to_status' => TaskStatus::Todo->value,
            'changed_by' => User::factory(),
            'occurred_at' => now(),
            'note' => null,
        ];
    }
}
