<?php

namespace App\Actions\Mail;

use App\Models\MailAccount;
use App\Models\MailSyncRun;
use App\Support\Mail\MailClient;
use Illuminate\Support\Str;
use Throwable;

class SyncMailAccount
{
    public function __construct(
        private MailClient $client,
        private ProcessInboundMessage $processMessage,
    ) {}

    /**
     * Import all messages from one mail account and record the run outcome.
     */
    public function handle(MailAccount $account): MailSyncRun
    {
        $run = MailSyncRun::create([
            'mail_account_id' => $account->id,
            'started_at' => now(),
            'processed' => 0,
            'failed' => 0,
        ]);

        $folder = (string) config('mail.inbound.folder', 'INBOX');

        try {
            $messages = $this->client->fetchMessages($account, $folder);
        } catch (Throwable $exception) {
            $run->update([
                'finished_at' => now(),
                'failed' => 1,
                'error' => $this->errorMessage($exception),
            ]);

            throw $exception;
        }

        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($messages as $message) {
            try {
                $this->processMessage->handle($account, $message);
                $processed++;
            } catch (Throwable $exception) {
                $failed++;
                $errors[] = $this->errorMessage($exception);
            }
        }

        $account->forceFill(['last_synced_at' => now()])->save();

        $run->update([
            'finished_at' => now(),
            'processed' => $processed,
            'failed' => $failed,
            'error' => $errors === [] ? null : implode(' | ', $errors),
        ]);

        return $run;
    }

    /**
     * Store a short, single-line error without credentials or stack traces.
     */
    private function errorMessage(Throwable $exception): string
    {
        return Str::limit(Str::squish($exception->getMessage()), 500, '');
    }
}
