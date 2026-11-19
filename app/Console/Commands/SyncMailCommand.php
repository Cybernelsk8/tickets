<?php

namespace App\Console\Commands;

use App\Jobs\SyncMailAccountJob;
use App\Models\MailAccount;
use Illuminate\Console\Command;

class SyncMailCommand extends Command
{
    /** @var string */
    protected $signature = 'tickets:sync-mail {--account=} {--due}';

    /** @var string */
    protected $description = 'Encola la sincronización IMAP de las cuentas de correo activas';

    /**
     * Queue synchronization jobs for the selected accounts.
     */
    public function handle(): int
    {
        $accounts = MailAccount::query()
            ->where('is_active', true)
            ->when($this->option('account'), fn ($query, mixed $accountId) => $query->where('id', $accountId))
            ->get();

        if ($this->option('due')) {
            $accounts = $accounts->filter(fn (MailAccount $account): bool => $account->last_synced_at === null
                || $account->last_synced_at->lessThanOrEqualTo(now()->subMinutes($account->sync_every_minutes)))
                ->values();
        }

        if ($accounts->isEmpty()) {
            $this->info('No hay cuentas de correo que sincronizar.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            SyncMailAccountJob::dispatch($account->id);
            $this->line("Sincronización encolada: {$account->name} (#{$account->id})");
        }

        return self::SUCCESS;
    }
}
