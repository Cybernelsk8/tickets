<?php

namespace App\Actions\Tasks;

use App\Actions\Tickets\CloseTicketWhenRootDone;
use App\Mail\TaskStatusChangedMail;
use App\Models\Task;
use App\Models\TaskStatusTransition;
use App\Models\User;
use App\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class MoveTask
{
    public function __construct(private CloseTicketWhenRootDone $closeTicketWhenRootDone) {}

    /**
     * Move a task to another board status, persisting its position and history.
     */
    public function handle(Task $task, TaskStatus $status, ?int $position = null): Task
    {
        Gate::authorize('move', $task);

        $previousStatus = $task->status;

        $statusChanged = $previousStatus !== $status;

        if ($statusChanged && ! $previousStatus->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'board' => __('No se puede mover la tarea de :from a :to.', [
                    'from' => $previousStatus->label(),
                    'to' => $status->label(),
                ]),
            ]);
        }

        if ($statusChanged
            && in_array($status, [TaskStatus::InProgress, TaskStatus::Testing, TaskStatus::Done], true)
            && $this->hasUnfinishedDependencies($task)) {
            throw ValidationException::withMessages([
                'board' => __('La tarea tiene dependencias que aún no están terminadas.'),
            ]);
        }

        if ($statusChanged
            && $status === TaskStatus::Done
            && config('tickets.require_evidence_before_done')
            && ! $task->comments()->exists()
            && ! $task->attachments()->exists()) {
            throw ValidationException::withMessages([
                'board' => __('La tarea necesita al menos un comentario o adjunto antes de terminarse.'),
            ]);
        }

        $requiresFinalReview = $statusChanged
            && $status === TaskStatus::Done
            && $task->parent_id === null
            && $task->children()->exists();

        if ($requiresFinalReview) {
            Gate::authorize('approve', $task);

            if ($this->hasUnfinishedSubtasks($task)) {
                throw ValidationException::withMessages([
                    'board' => __('La tarea tiene subtareas que aún no están terminadas.'),
                ]);
            }
        }

        DB::transaction(function () use ($task, $status, $position, $previousStatus, $statusChanged): void {
            $task->update($this->statusAttributes($task, $status));

            if ($status === TaskStatus::Done) {
                $this->closeTicketWhenRootDone->handle($task);
            }

            $columnQuery = Task::query()
                ->where('area_id', $task->area_id)
                ->where('status', $status->value);

            if ($status === TaskStatus::Done) {
                $columnQuery->where(function (Builder $query): void {
                    $query->where('completed_at', '>=', now()->subDays(7))
                        ->orWhereNull('completed_at');
                });
            }

            $columnTasks = $columnQuery
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->reject(fn (Task $columnTask): bool => $columnTask->is($task))
                ->values();

            $index = $position === null
                ? $columnTasks->count()
                : max(0, min($position, $columnTasks->count()));

            $columnTasks->splice($index, 0, [$task]);

            foreach ($columnTasks as $orderedIndex => $orderedTask) {
                $orderedTask->update(['position' => $orderedIndex]);
            }

            if ($statusChanged) {
                TaskStatusTransition::create([
                    'task_id' => $task->id,
                    'from_status' => $previousStatus->value,
                    'to_status' => $status->value,
                    'changed_by' => auth()->id(),
                    'occurred_at' => now(),
                    'note' => $position === null ? null : __('Movida en el tablero Kanban.'),
                ]);
            }
        });

        $task->refresh();

        if ($statusChanged
            && in_array($status, [TaskStatus::InProgress, TaskStatus::Testing, TaskStatus::Done], true)) {
            $this->notifySupervisor($task, $previousStatus, $status);
        }

        return $task;
    }

    /**
     * Notify the area manager when a task reaches a relevant stage.
     */
    private function notifySupervisor(Task $task, TaskStatus $previousStatus, TaskStatus $status): void
    {
        $manager = $task->area?->manager;

        if (! $manager instanceof User || $manager->id === auth()->id()) {
            return;
        }

        Mail::to($manager->email)->queue(new TaskStatusChangedMail($task, $manager, $previousStatus, $status));
    }

    /**
     * @return array<string, mixed>
     */
    private function statusAttributes(Task $task, TaskStatus $status): array
    {
        $active = in_array($status, [TaskStatus::InProgress, TaskStatus::Testing, TaskStatus::Done], true);
        $testing = in_array($status, [TaskStatus::Testing, TaskStatus::Done], true);

        return [
            'status' => $status,
            'started_at' => $active ? ($task->started_at ?? now()) : null,
            'testing_at' => $testing ? ($task->testing_at ?? now()) : null,
            'completed_at' => $status === TaskStatus::Done ? now() : null,
        ];
    }

    /**
     * Determine whether a finish-to-start dependency is still open.
     */
    private function hasUnfinishedDependencies(Task $task): bool
    {
        return $task->dependencies()
            ->whereHas('dependsOnTask', fn ($query) => $query->where('status', '!=', TaskStatus::Done->value))
            ->exists();
    }

    /**
     * Determine whether any subtask still prevents the final review.
     */
    private function hasUnfinishedSubtasks(Task $task): bool
    {
        return $task->children()
            ->whereNot('status', TaskStatus::Done->value)
            ->exists();
    }
}
