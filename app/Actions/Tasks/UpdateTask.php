<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\TicketPriority;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateTask
{
    /**
     * Update a task, its assignment, parent and primary dependency.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Task $task, array $data): Task
    {
        Gate::authorize('update', $task);

        /** @var array{title: string, description: string|null, priority: string, due_at: string|null, estimated_minutes: int|null, assignee_id: int|null, parent_id: int|null, depends_on_task_id: int|null} $validated */
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(array_map(
                fn (TicketPriority $case): string => $case->value,
                TicketPriority::cases(),
            ))],
            'due_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'parent_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'depends_on_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
        ])->validate();

        $assigneeId = $this->resolveAssignee($task, $validated['assignee_id'], $data);
        $parent = $this->resolveParent($task, $validated['parent_id']);
        $dependency = $this->resolveDependency($task, $validated['depends_on_task_id']);

        DB::transaction(function () use ($task, $validated, $assigneeId, $parent, $dependency): void {
            $task->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?: null,
                'priority' => TicketPriority::from($validated['priority']),
                'due_at' => $validated['due_at'] === null ? null : Carbon::parse($validated['due_at']),
                'estimated_minutes' => $validated['estimated_minutes'],
                'assignee_id' => $assigneeId,
                'parent_id' => $parent?->id,
            ]);

            TaskDependency::query()
                ->where('task_id', $task->id)
                ->where('type', 'finish_to_start')
                ->delete();

            if ($dependency !== null) {
                TaskDependency::create([
                    'task_id' => $task->id,
                    'depends_on_task_id' => $dependency->id,
                    'type' => 'finish_to_start',
                ]);
            }
        });

        return $task->fresh();
    }

    /**
     * Resolve the responsible user, honoring the reassign ability.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveAssignee(Task $task, ?int $requestedAssigneeId, array $data): ?int
    {
        if (! Gate::allows('reassign', $task)) {
            $provided = array_key_exists('assignee_id', $data) && $data['assignee_id'] !== null
                ? (int) $data['assignee_id']
                : null;

            if ($provided !== $task->assignee_id) {
                throw ValidationException::withMessages([
                    'editAssigneeId' => __('No tienes permiso para cambiar el responsable.'),
                ]);
            }

            return $task->assignee_id;
        }

        if ($requestedAssigneeId === null) {
            return null;
        }

        $assignee = User::query()->findOrFail($requestedAssigneeId);

        if ($task->area_id !== null && $assignee->area_id !== $task->area_id) {
            throw ValidationException::withMessages([
                'editAssigneeId' => __('El responsable debe pertenecer al área de la tarea.'),
            ]);
        }

        return $assignee->id;
    }

    /**
     * Resolve a parent task without creating a parent cycle.
     */
    private function resolveParent(Task $task, ?int $parentId): ?Task
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Task::query()->findOrFail($parentId);

        if ($parent->is($task)) {
            throw ValidationException::withMessages([
                'editParentTaskId' => __('Una tarea no puede ser su propia tarea padre.'),
            ]);
        }

        if ($task->area_id !== null && $parent->area_id !== $task->area_id) {
            throw ValidationException::withMessages([
                'editParentTaskId' => __('La tarea padre debe pertenecer al área de la tarea.'),
            ]);
        }

        if ($this->isDescendantOf($parent, $task)) {
            throw ValidationException::withMessages([
                'editParentTaskId' => __('La tarea padre seleccionada es una subtarea de esta tarea.'),
            ]);
        }

        return $parent;
    }

    /**
     * Resolve a dependency without creating a dependency cycle.
     */
    private function resolveDependency(Task $task, ?int $dependencyId): ?Task
    {
        if ($dependencyId === null) {
            return null;
        }

        $dependency = Task::query()->findOrFail($dependencyId);

        if ($dependency->is($task)) {
            throw ValidationException::withMessages([
                'editDependsOnTaskId' => __('Una tarea no puede depender de sí misma.'),
            ]);
        }

        if ($task->area_id !== null && $dependency->area_id !== $task->area_id) {
            throw ValidationException::withMessages([
                'editDependsOnTaskId' => __('La dependencia debe pertenecer al área de la tarea.'),
            ]);
        }

        if ($this->dependsOnTransitively($dependency, $task)) {
            throw ValidationException::withMessages([
                'editDependsOnTaskId' => __('La dependencia seleccionada crearía un ciclo.'),
            ]);
        }

        return $dependency;
    }

    /**
     * Determine whether the candidate is below the given ancestor.
     */
    private function isDescendantOf(Task $candidate, Task $ancestor): bool
    {
        $current = $candidate;
        $visited = [];

        while ($current->parent_id !== null) {
            if ($current->parent_id === $ancestor->id) {
                return true;
            }

            if (isset($visited[$current->parent_id])) {
                return false;
            }

            $visited[$current->parent_id] = true;

            $next = Task::query()->find($current->parent_id);

            if (! $next instanceof Task) {
                return false;
            }

            $current = $next;
        }

        return false;
    }

    /**
     * Determine whether the starting task already depends on the target task.
     */
    private function dependsOnTransitively(Task $start, Task $target): bool
    {
        /** @var array<int, int|null> $queue */
        $queue = [$start->id];
        $visited = [];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if ($currentId === null) {
                break;
            }

            if ($currentId === $target->id) {
                return true;
            }

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;

            $dependsOnIds = TaskDependency::query()
                ->where('task_id', $currentId)
                ->pluck('depends_on_task_id');

            foreach ($dependsOnIds as $dependsOnId) {
                $queue[] = $dependsOnId === null ? null : (int) $dependsOnId;
            }
        }

        return false;
    }
}
