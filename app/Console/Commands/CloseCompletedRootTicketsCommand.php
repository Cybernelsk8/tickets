<?php

namespace App\Console\Commands;

use App\Actions\Tickets\CloseTicketWhenRootDone;
use App\Models\Task;
use App\Models\Ticket;
use App\TaskStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('tickets:close-completed-roots {--dry-run : Solo muestra los tickets que se cerrarían sin modificarlos}')]
#[Description('Cierra los tickets cuya tarea raíz ya está terminada')]
class CloseCompletedRootTicketsCommand extends Command
{
    public function __construct(private readonly CloseTicketWhenRootDone $closeTicketWhenRootDone)
    {
        parent::__construct();
    }

    /**
     * Repair tickets left open after their root task was delivered outside the board action.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $closed = 0;

        Task::query()
            ->roots()
            ->where('status', TaskStatus::Done->value)
            ->whereHas('ticket', fn (Builder $query): Builder => $query->whereNot('status', TaskStatus::Done->value))
            ->with('ticket')
            ->chunkById(100, function (Collection $tasks) use (&$closed, $dryRun): void {
                foreach ($tasks as $task) {
                    $ticket = $task->ticket;

                    if (! $ticket instanceof Ticket) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("{$ticket->code} — {$ticket->subject}");
                    } else {
                        DB::transaction(fn (): ?Ticket => $this->closeTicketWhenRootDone->handle($task, $task->completed_at));
                    }

                    $closed++;
                }
            });

        $this->info(($dryRun ? 'Tickets que se cerrarían' : 'Tickets cerrados').": {$closed}");

        return self::SUCCESS;
    }
}
