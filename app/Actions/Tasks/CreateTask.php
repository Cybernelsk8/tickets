<?php

namespace App\Actions\Tasks;

use App\Models\Area;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TaskStatusTransition;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateTask
{
    /**
     * Create a root task or subtask directly from a board column.
     *
     * @param  array{
     *     title: string,
     *     description: string|null,
     *     area_id: int,
     *     parent_id: int|null,
     *     assignee_id: int|null,
     *     status: TaskStatus,
     *     priority: TicketPriority,
     *     estimated_minutes: int|null,
     *     actual_minutes: int|null,
     *     due_at: Carbon|null,
     *     depends_on_task_id: int|null
     * }  $data
     */
    public function handle(array $data): Task
    {
        Gate::authorize('create', Task::class);

        $area = Area::query()->findOrFail($data['area_id']);
        $this->authorizeArea($area);

        $parent = $data['parent_id'] === null
            ? null
            : Task::query()->findOrFail($data['parent_id']);

        if ($parent !== null && $parent->area_id !== $area->id) {
            throw ValidationException::withMessages([
                'parent_id' => __('La tarea padre debe pertenecer al área seleccionada.'),
            ]);
        }

        if ($parent !== null) {
            Gate::authorize('update', $parent);
        }

        $assignee = $data['assignee_id'] === null
            ? null
            : User::query()->findOrFail($data['assignee_id']);

        if ($assignee !== null && $assignee->area_id !== $area->id) {
            throw ValidationException::withMessages([
                'assignee_id' => __('El responsable debe pertenecer al área seleccionada.'),
            ]);
        }

        $dependency = $data['depends_on_task_id'] === null
            ? null
            : Task::query()->findOrFail($data['depends_on_task_id']);

        if ($dependency !== null && $dependency->area_id !== $area->id) {
            throw ValidationException::withMessages([
                'depends_on_task_id' => __('La dependencia debe pertenecer al área seleccionada.'),
            ]);
        }

        if ($dependency !== null
            && in_array($data['status'], [TaskStatus::InProgress, TaskStatus::Testing, TaskStatus::Done], true)
            && $dependency->status !== TaskStatus::Done) {
            throw ValidationException::withMessages([
                'depends_on_task_id' => __('La dependencia debe estar terminada para iniciar esta tarea.'),
            ]);
        }

        return DB::transaction(function () use ($data, $area, $assignee, $dependency): Task {
            $nextPosition = (int) Task::query()
                ->where('area_id', $area->id)
                ->where('status', $data['status']->value)
                ->max('position') + 1;

            $task = Task::create([
                'ticket_id' => null,
                'parent_id' => $data['parent_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'area_id' => $area->id,
                'assignee_id' => $assignee?->id,
                'status' => $data['status'],
                'priority' => $data['priority'],
                'estimated_minutes' => $data['estimated_minutes'],
                'actual_minutes' => $data['actual_minutes'],
                'position' => $nextPosition,
                'due_at' => $data['due_at'],
                'created_by' => auth()->id(),
            ]);

            if ($dependency !== null) {
                TaskDependency::create([
                    'task_id' => $task->id,
                    'depends_on_task_id' => $dependency->id,
                    'type' => 'finish_to_start',
                ]);
            }

            TaskStatusTransition::create([
                'task_id' => $task->id,
                'from_status' => null,
                'to_status' => $data['status']->value,
                'changed_by' => auth()->id(),
                'occurred_at' => now(),
                'note' => __('Tarea creada desde el tablero.'),
            ]);

            return $task;
        });
    }

    /**
     * Restrict task creation to areas the user manages.
     */
    private function authorizeArea(Area $area): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'area_id' => __('Debes iniciar sesión para crear tareas.'),
            ]);
        }

        if ($user->hasRole('super_admin') || $user->managedAreas()->whereKey($area->id)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'area_id' => __('Solo puedes crear tareas en un área que gestionas.'),
        ]);
    }
}
