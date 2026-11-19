<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'parent_id' => null,
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'assignee_id' => null,
            'area_id' => Area::factory(),
            'status' => TaskStatus::Todo,
            'priority' => TicketPriority::Medium,
            'estimated_minutes' => null,
            'actual_minutes' => null,
            'position' => 0,
            'due_at' => null,
            'started_at' => null,
            'testing_at' => null,
            'completed_at' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Make the task a subtask of the given task.
     */
    public function childOf(Task $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'ticket_id' => $parent->ticket_id,
            'area_id' => $parent->area_id,
        ]);
    }

    /**
     * Indicate that the task is assigned to a user.
     */
    public function assignedTo(User $assignee): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_id' => $assignee->id,
            'status' => TaskStatus::Assigned,
        ]);
    }

    /**
     * Indicate that the task is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the task is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Done,
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }
}
