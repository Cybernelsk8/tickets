<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskStatusTransition;
use App\TaskStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MoveTask
{
    /**
     * Move a task to another board status, persisting its position and history.
     */
    public function handle(Task $task, TaskStatus $status, ?int $position = null): Task
    {
        Gate::authorize('move', $task);

        $previousStatus = $task->status;

        if (! $previousStatus->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'board' => __('No se puede mover la tarea de :from a :to.', [
                    'from' => $previousStatus->label(),
                    'to' => $status->label(),
                ]),
            ]);
        }

        if (in_array($status, [TaskStatus::InProgress, TaskStatus::Testing, TaskStatus::Done], true)
            && $this->hasUnfinishedDependencies($task)) {
            throw ValidationException::withMessages([
                'board' => __('La tarea tiene dependencias que aún no están terminadas.'),
            ]);
        }

        DB::transaction(function () use ($task, $status, $position, $previousStatus): void {
            $task->update($this->statusAttributes($task, $status));

            $columnTasks = Task::query()
                ->where('area_id', $task->area_id)
                ->where('status', $status->value)
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

            if ($previousStatus !== $status) {
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

        return $task->fresh();
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
}
