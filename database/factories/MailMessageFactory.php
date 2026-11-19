<?php

namespace Database\Factories;

use App\Models\MailAccount;
use App\Models\MailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailMessage>
 */
class MailMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mail_account_id' => MailAccount::factory(),
            'folder' => 'INBOX',
            'external_uid' => (string) fake()->unique()->numberBetween(1, 999999),
            'message_id' => fake()->unique()->uuid().'@example.com',
            'thread_key' => fake()->unique()->uuid(),
            'parent_message_id' => null,
            'subject' => fake()->sentence(),
            'from_name' => fake()->name(),
            'from_email' => fake()->safeEmail(),
            'recipients' => fake()->safeEmail(),
            'cc_recipients' => null,
            'body_text' => fake()->paragraph(),
            'body_html' => null,
            'received_at' => now(),
            'is_read' => false,
            'synced_at' => now(),
        ];
    }
}
