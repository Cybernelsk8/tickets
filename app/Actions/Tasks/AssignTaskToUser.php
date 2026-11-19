<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignTaskToUser
{
    /**
     * Assign a board task directly to a collaborator of its area.
     */
    public function handle(Task $task, ?int $assigneeId): Task
    {
        Gate::authorize('update', $task);

        $assignee = $assigneeId === null
            ? null
            : User::query()->findOrFail($assigneeId);

        if ($assignee !== null
            && $task->area_id !== null
            && $assignee->area_id !== $task->area_id) {
            throw ValidationException::withMessages([
                'assignee_id' => __('El responsable debe pertenecer al área de la tarea.'),
            ]);
        }

        $task->update(['assignee_id' => $assignee?->id]);

        return $task->fresh();
    }
}
