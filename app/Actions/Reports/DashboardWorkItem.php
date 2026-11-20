<?php

namespace App\Actions\Reports;

use App\Models\Task;
use App\Models\Ticket;
use App\TaskStatus;
use App\TicketPriority;
use Carbon\CarbonInterface;

/**
 * Fila unificada del dashboard: ticket, tarea raíz o ticket con tarea raíz.
 *
 * Las subtareas nunca son filas independientes; se resumen en el progreso y
 * se consultan al expandir la fila desde Livewire.
 */
final class DashboardWorkItem
{
    /**
     * @param  string  $key  Identificador estable de la fila: `task-{id}` o `ticket-{id}`.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $sourceLabel,
        public readonly ?int $ticketId,
        public readonly ?int $taskId,
        public readonly ?string $code,
        public readonly string $displayTitle,
        public readonly string $subject,
        public readonly ?string $description,
        public readonly ?string $areaName,
        public readonly ?string $categoryName,
        public readonly ?TicketPriority $priority,
        public readonly ?string $assigneeName,
        public readonly ?string $requesterName,
        public readonly ?string $requesterEmail,
        public readonly ?string $createdByName,
        public readonly TaskStatus $status,
        public readonly ?string $statusChangedByName,
        public readonly ?string $statusChangedAt,
        public readonly string $createdAt,
        public readonly ?string $assignedAt,
        public readonly ?string $closedAt,
        public readonly string $activityAt,
        public readonly int $activityTimestamp,
        public readonly bool $isReadyForReview,
        public readonly bool $hasSubtasks,
        public readonly int $subtasksDone,
        public readonly int $subtasksTotal,
        public readonly float $progressPercent,
        public readonly string $progressColor,
        public readonly string $progressLabel,
        public readonly bool $showProgressBar,
    ) {}

    /**
     * Build a row from a root task. When the row was found through a subtask,
     * pass that subtask as the activity source so the parent is ordered by the
     * most recent work without changing the deliverable's own status.
     */
    public static function fromTask(Task $task, ?Ticket $ticket = null, ?Task $activitySource = null): self
    {
        $ticket ??= $task->ticket;
        $activity = $activitySource ?? $task;
        $taskAreaName = $task->area?->name;
        $ticketAreaName = $ticket?->area?->name;
        $taskAssigneeName = $task->assignee?->name;
        $ticketAssigneeName = $ticket?->assignee?->name;
        $taskCreatorName = $task->creator?->name;
        $ticketCreatorName = $ticket?->creator?->name;
        $subtasksTotal = (int) ($task->subtasks_total_count ?? 0);
        $subtasksDone = (int) ($task->subtasks_done_count ?? 0);
        $hasSubtasks = $subtasksTotal > 0;
        $progressPercent = $hasSubtasks
            ? round($subtasksDone / $subtasksTotal * 100, 1)
            : ($task->status === TaskStatus::Done ? 100.0 : 0.0);
        $lastTransition = $task->latestStatusTransition;
        $activityDate = $activity->completed_at ?? $activity->updated_at ?? $activity->created_at;

        return new self(
            key: 'task-'.$task->id,
            sourceLabel: $ticket === null ? __('Tarea') : __('Ticket'),
            ticketId: $ticket?->id,
            taskId: $task->id,
            code: $ticket?->code,
            displayTitle: $ticket?->code === null ? $task->title : $ticket->code.' — '.$task->title,
            subject: $task->title,
            description: $task->description ?: $ticket?->description,
            areaName: $taskAreaName ?? $ticketAreaName,
            categoryName: $ticket?->category?->name,
            priority: $task->priority ?? $ticket?->priority,
            assigneeName: $taskAssigneeName ?? $ticketAssigneeName,
            requesterName: $ticket?->requester_name,
            requesterEmail: $ticket?->requester_email,
            createdByName: $taskCreatorName ?? $ticketCreatorName,
            status: $task->status,
            statusChangedByName: $lastTransition?->changedBy?->name,
            statusChangedAt: self::formatDateTime($lastTransition?->occurred_at),
            createdAt: self::formatDateTime($ticket?->created_at) ?? self::formatDateTime($task->created_at) ?? '',
            assignedAt: self::formatDateTime($ticket?->assigned_at),
            closedAt: self::formatDateTime($ticket?->closed_at),
            activityAt: self::formatDateTime($activityDate) ?? '',
            activityTimestamp: $activityDate?->getTimestamp() ?? 0,
            isReadyForReview: $hasSubtasks
                && $subtasksDone === $subtasksTotal
                && $task->status !== TaskStatus::Done,
            hasSubtasks: $hasSubtasks,
            subtasksDone: $subtasksDone,
            subtasksTotal: $subtasksTotal,
            progressPercent: $progressPercent,
            progressColor: $hasSubtasks
                ? self::progressColor((int) round($progressPercent))
                : ($task->status === TaskStatus::Done ? 'green' : 'zinc'),
            progressLabel: $hasSubtasks
                ? $subtasksDone.'/'.$subtasksTotal.' '.__('subtareas')
                : __('Sin desglose'),
            showProgressBar: $hasSubtasks || $task->status === TaskStatus::Done,
        );
    }

    /**
     * Build a row for a ticket that still has no root task.
     */
    public static function fromTicket(Ticket $ticket): self
    {
        $activityDate = $ticket->closed_at ?? $ticket->assigned_at ?? $ticket->updated_at ?? $ticket->created_at;
        $isDone = $ticket->status === TaskStatus::Done;
        $assigneeName = $ticket->assignee?->name;
        $creatorName = $ticket->creator?->name;

        return new self(
            key: 'ticket-'.$ticket->id,
            sourceLabel: __('Ticket'),
            ticketId: $ticket->id,
            taskId: null,
            code: $ticket->code,
            displayTitle: $ticket->code.' — '.$ticket->subject,
            subject: $ticket->subject,
            description: $ticket->description,
            areaName: $ticket->area?->name,
            categoryName: $ticket->category?->name,
            priority: $ticket->priority,
            assigneeName: $assigneeName ?? __('Sin asignar'),
            requesterName: $ticket->requester_name,
            requesterEmail: $ticket->requester_email,
            createdByName: $creatorName,
            status: $ticket->status,
            statusChangedByName: $assigneeName ?? $creatorName,
            statusChangedAt: self::formatDateTime($ticket->assigned_at ?? $ticket->created_at),
            createdAt: self::formatDateTime($ticket->created_at) ?? '',
            assignedAt: self::formatDateTime($ticket->assigned_at),
            closedAt: self::formatDateTime($ticket->closed_at),
            activityAt: self::formatDateTime($activityDate) ?? '',
            activityTimestamp: $activityDate?->getTimestamp() ?? 0,
            isReadyForReview: false,
            hasSubtasks: false,
            subtasksDone: 0,
            subtasksTotal: 0,
            progressPercent: $isDone ? 100.0 : 0.0,
            progressColor: $isDone ? 'green' : 'zinc',
            progressLabel: __('Sin desglose'),
            showProgressBar: $isDone,
        );
    }

    /**
     * Whether this row corresponds to a ticket received from the mailbox.
     */
    public function isTicket(): bool
    {
        return $this->ticketId !== null;
    }

    private static function formatDateTime(?CarbonInterface $date): ?string
    {
        return $date?->format('d/m/Y H:i');
    }

    private static function progressColor(int $percent): string
    {
        return match (true) {
            $percent >= 100 => 'green',
            $percent >= 50 => 'yellow',
            $percent >= 25 => 'orange',
            default => 'red',
        };
    }
}
