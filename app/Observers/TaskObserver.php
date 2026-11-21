<?php

namespace App\Observers;

use App\Actions\Tickets\CloseTicketWhenRootDone;
use App\Models\Task;
use App\TaskStatus;

class TaskObserver
{
    public function __construct(private readonly CloseTicketWhenRootDone $closeTicketWhenRootDone) {}

    /**
     * Keep the linked ticket in sync whenever a root task is delivered.
     */
    public function saved(Task $task): void
    {
        if ($task->status !== TaskStatus::Done
            || $task->parent_id !== null
            || $task->ticket_id === null
            || (! $task->wasRecentlyCreated && ! $task->wasChanged('status'))) {
            return;
        }

        $this->closeTicketWhenRootDone->handle($task);
    }
}
