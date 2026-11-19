<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'TKT-'.fake()->unique()->numberBetween(1, 999999),
            'source' => 'email',
            'mail_message_id' => null,
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'requester_name' => fake()->name(),
            'requester_email' => fake()->safeEmail(),
            'area_id' => Area::factory(),
            'category_id' => TicketCategory::factory(),
            'priority' => TicketPriority::Medium,
            'status' => TaskStatus::Todo,
            'assignee_id' => null,
            'created_by' => User::factory(),
            'due_at' => null,
            'assigned_at' => null,
            'closed_at' => null,
        ];
    }

    /**
     * Indicate that the ticket was created manually.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'manual',
            'mail_message_id' => null,
        ]);
    }

    /**
     * Indicate that the ticket has been assigned.
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Assigned,
            'assignee_id' => User::factory(),
            'assigned_at' => now(),
        ]);
    }
}
