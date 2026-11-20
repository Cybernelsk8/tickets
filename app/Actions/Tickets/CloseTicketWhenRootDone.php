<?php

namespace App\Actions\Tickets;

use App\Models\Task;
use App\Models\Ticket;
use App\TaskStatus;

class CloseTicketWhenRootDone
{
    /**
     * Close the ticket linked to a root task that has just been delivered.
     */
    public function handle(Task $task): ?Ticket
    {
        if ($task->parent_id !== null
            || $task->status !== TaskStatus::Done
            || $task->ticket_id === null) {
            return null;
        }

        $ticket = $task->ticket;

        if (! $ticket instanceof Ticket || $ticket->status->isFinal()) {
            return null;
        }

        $ticket->update([
            'status' => TaskStatus::Done,
            'closed_at' => $ticket->closed_at ?? now(),
        ]);

        return $ticket->refresh();
    }
}
