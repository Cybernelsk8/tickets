<?php

namespace Database\Factories;

use App\Models\MailAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailAccount>
 */
class MailAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Mail',
            'email' => fake()->unique()->safeEmail(),
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'username' => fake()->userName(),
            'credentials' => 'secret-password',
            'owner_user_id' => null,
            'is_active' => true,
            'sync_every_minutes' => 15,
            'last_synced_at' => null,
        ];
    }

    /**
     * Indicate that the account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
