<?php

namespace Database\Factories;

use App\Models\MailAccount;
use App\Models\MailSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailSyncRun>
 */
class MailSyncRunFactory extends Factory
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
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'processed' => 0,
            'failed' => 0,
            'error' => null,
        ];
    }
}
