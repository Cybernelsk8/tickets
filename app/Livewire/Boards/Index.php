<?php

namespace App\Livewire\Boards;

use App\Actions\Tasks\AssignTaskToUser;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\MoveTask;
use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public ?int $areaId = null;

    public string $title = '';

    public ?string $description = null;

    public ?int $parentTaskId = null;

    public ?int $assigneeId = null;

    public string $priority = 'medium';

    public ?int $estimatedMinutes = null;

    public ?int $actualMinutes = null;

    public ?string $dueAt = null;

    public ?int $dependsOnTaskId = null;

    public string $targetStatus = 'todo';

    public bool $showCreateModal = false;

    public ?int $assignmentTaskId = null;

    public ?int $assignmentAssigneeId = null;

    public bool $showAssignmentModal = false;

    public ?int $detailsTaskId = null;

    public bool $showDetailsModal = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', Task::class);

        $this->areaId = $this->initialAreaId();
    }

    /**
     * Prefer the user's area, then the area with tasks, so assigned tickets are visible.
     */
    private function initialAreaId(): ?int
    {
        $areas = $this->areas();

        if ($areas->isEmpty()) {
            return null;
        }

        $user = auth()->user();
        $userAreaId = $user instanceof User ? $user->area_id : null;

        if ($userAreaId !== null && $areas->contains(fn (Area $area): bool => $area->id === $userAreaId)) {
            return $userAreaId;
        }

        $areaIdWithTasks = Task::query()
            ->whereIn('area_id', $areas->pluck('id'))
            ->select('area_id')
            ->selectRaw('COUNT(*) as tasks_count')
            ->groupBy('area_id')
            ->orderByDesc('tasks_count')
            ->value('area_id');

        if ($areaIdWithTasks !== null) {
            return (int) $areaIdWithTasks;
        }

        return $areas->first()->id;
    }

    /**
     * @return Collection<int, Area>
     */
    #[Computed]
    public function areas(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        $query = Area::query()->where('is_active', true);

        if ($user->hasRole('super_admin') || $user->can('tickets.viewAny')) {
            return $query->orderBy('name')->get();
        }

        $managedAreaIds = $user->managedAreas()->where('is_active', true)->pluck('areas.id');

        if ($managedAreaIds->isNotEmpty()) {
            return Area::query()->whereIn('id', $managedAreaIds)->orderBy('name')->get();
        }

        if ($user->area_id !== null) {
            return (clone $query)->whereKey($user->area_id)->get();
        }

        return collect();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        if ($this->areaId === null) {
            return collect();
        }

        return Task::query()
            ->with(['ticket', 'assignee', 'parent', 'dependencies.dependsOnTask'])
            ->where('area_id', $this->areaId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, array{id: string, label: string, status: TaskStatus, tasks: Collection<int, Task>}>
     */
    #[Computed]
    public function columns(): array
    {
        return collect([
            ['id' => 'todo', 'label' => TaskStatus::Todo->label(), 'status' => TaskStatus::Todo],
            ['id' => 'assigned', 'label' => TaskStatus::Assigned->label(), 'status' => TaskStatus::Assigned],
            ['id' => 'in_progress', 'label' => TaskStatus::InProgress->label(), 'status' => TaskStatus::InProgress],
            ['id' => 'done', 'label' => TaskStatus::Done->label(), 'status' => TaskStatus::Done],
        ])->map(function (array $column): array {
            $column['tasks'] = $this->tasks()
                ->filter(fn (Task $task): bool => $task->status->kanbanColumn() === $column['id'])
                ->values();

            return $column;
        })->all();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignees(): Collection
    {
        if ($this->areaId === null) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where('area_id', $this->areaId)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function availableTasks(): Collection
    {
        if ($this->areaId === null) {
            return collect();
        }

        return Task::query()
            ->where('area_id', $this->areaId)
            ->orderBy('title')
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function parentTasks(): Collection
    {
        return $this->availableTasks()->filter(fn (Task $task): bool => $task->parent_id === null)->values();
    }

    public function detailsTask(): ?Task
    {
        if ($this->detailsTaskId === null) {
            return null;
        }

        return Task::query()
            ->with([
                'ticket',
                'assignee',
                'parent',
                'children',
                'dependencies.dependsOnTask',
                'statusTransitions.changedBy',
            ])
            ->find($this->detailsTaskId);
    }

    public function updatedAreaId(): void
    {
        $this->closeModals();
    }

    public function openCreate(string $column): void
    {
        Gate::authorize('create', Task::class);

        $this->resetCreateForm();
        $this->targetStatus = in_array($column, ['todo', 'assigned', 'in_progress', 'done'], true)
            ? $column
            : 'todo';
        $this->showDetailsModal = false;
        $this->showCreateModal = true;
    }

    public function createTask(CreateTask $action): void
    {
        Gate::authorize('create', Task::class);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parentTaskId' => ['nullable', 'integer', 'exists:tasks,id'],
            'assigneeId' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['required', Rule::in(array_map(
                fn (TicketPriority $case): string => $case->value,
                TicketPriority::cases(),
            ))],
            'estimatedMinutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'actualMinutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'dueAt' => ['nullable', 'date'],
            'dependsOnTaskId' => ['nullable', 'integer', 'exists:tasks,id'],
            'targetStatus' => ['required', Rule::in(['todo', 'assigned', 'in_progress', 'done'])],
            'areaId' => ['required', 'integer', 'exists:areas,id'],
        ]);

        try {
            $action->handle([
                'title' => $validated['title'],
                'description' => $validated['description'] ?: null,
                'area_id' => $validated['areaId'],
                'parent_id' => $validated['parentTaskId'],
                'assignee_id' => $validated['assigneeId'],
                'status' => TaskStatus::from($validated['targetStatus']),
                'priority' => TicketPriority::from($validated['priority']),
                'estimated_minutes' => $validated['estimatedMinutes'],
                'actual_minutes' => $validated['actualMinutes'],
                'due_at' => $validated['dueAt'] === null ? null : Carbon::parse($validated['dueAt']),
                'depends_on_task_id' => $validated['dependsOnTaskId'],
            ]);
        } catch (ValidationException $exception) {
            $this->reportValidationError($exception);

            return;
        }

        $this->closeModals();
        Flux::toast(text: __('Tarea creada en el tablero.'), variant: 'success');
    }

    public function openDetails(int $taskId): void
    {
        $task = Task::findOrFail($taskId);
        Gate::authorize('view', $task);

        $this->detailsTaskId = $task->id;
        $this->showDetailsModal = true;
    }

    public function openAssign(int $taskId): void
    {
        $task = Task::findOrFail($taskId);
        Gate::authorize('update', $task);

        $this->assignmentTaskId = $task->id;
        $this->assignmentAssigneeId = $task->assignee_id;
        $this->showDetailsModal = false;
        $this->showAssignmentModal = true;
    }

    public function assignTask(AssignTaskToUser $action): void
    {
        if ($this->assignmentTaskId === null) {
            return;
        }

        $task = Task::findOrFail($this->assignmentTaskId);
        Gate::authorize('update', $task);

        $validated = $this->validate([
            'assignmentAssigneeId' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        try {
            $action->handle($task, $validated['assignmentAssigneeId']);
        } catch (ValidationException $exception) {
            $this->reportValidationError($exception);

            return;
        }

        $this->closeModals();
        Flux::toast(text: __('Responsable actualizado.'), variant: 'success');
    }

    public function handleSort(int $taskId, int $position, string $column): void
    {
        $status = match ($column) {
            'todo' => TaskStatus::Todo,
            'assigned' => TaskStatus::Assigned,
            'in_progress' => TaskStatus::InProgress,
            'done' => TaskStatus::Done,
            default => null,
        };

        if ($status === null) {
            return;
        }

        $this->moveTask($taskId, $status->value, $position);
    }

    public function moveTask(int $taskId, string $status, ?int $position = null): void
    {
        $task = Task::findOrFail($taskId);

        try {
            app(MoveTask::class)->handle($task, TaskStatus::from($status), $position);
        } catch (ValidationException $exception) {
            $this->reportValidationError($exception);
        }
    }

    public function render(): View
    {
        return view('livewire.boards.index');
    }

    private function closeModals(): void
    {
        Flux::modal('create-task')->close();
        Flux::modal('assign-task')->close();

        $this->showCreateModal = false;
        $this->showAssignmentModal = false;
        $this->detailsTaskId = null;
        $this->showDetailsModal = false;
    }

    private function resetCreateForm(): void
    {
        $this->reset([
            'title',
            'description',
            'parentTaskId',
            'assigneeId',
            'priority',
            'estimatedMinutes',
            'actualMinutes',
            'dueAt',
            'dependsOnTaskId',
        ]);

        $this->priority = 'medium';
    }

    private function reportValidationError(ValidationException $exception): void
    {
        $this->addError('board', $exception->validator->errors()->first() ?: $exception->getMessage());
    }
}
