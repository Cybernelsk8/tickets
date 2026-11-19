<?php

namespace App\Jobs;

use App\Actions\Mail\SyncMailAccount;
use App\Models\MailAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncMailAccountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 60];

    public int $timeout = 180;

    public function __construct(public int $mailAccountId) {}

    public function handle(SyncMailAccount $sync): void
    {
        $account = MailAccount::find($this->mailAccountId);

        if ($account === null || ! $account->is_active) {
            return;
        }

        $sync->handle($account);
    }

    public function failed(Throwable $exception): void
    {
        // The synchronization action already records the failure in mail_sync_runs.
    }
}
