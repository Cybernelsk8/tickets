<?php

namespace App\Livewire\Boards;

use App\Actions\Tasks\AddTaskComment;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\MoveTask;
use App\Actions\Tasks\UpdateTask;
use App\Models\Area;
use App\Models\Comment;
use App\Models\Task;
use App\Models\TaskStatusTransition;
use App\Models\User;
use App\TaskBoardColumn;
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
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    #[Url(except: '')]
    public string $scope = '';

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

    public ?int $detailsTaskId = null;

    public bool $showDetailsModal = false;

    public string $commentBody = '';

    public bool $commentIsInternal = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $commentFiles = [];

    public ?int $editTaskId = null;

    public ?int $editAreaId = null;

    public string $editTitle = '';

    public ?string $editDescription = null;

    public string $editPriority = 'medium';

    public ?string $editDueAt = null;

    public ?int $editEstimatedMinutes = null;

    public ?int $editAssigneeId = null;

    public ?int $editParentTaskId = null;

    public ?int $editDependsOnTaskId = null;

    public bool $showEditModal = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', Task::class);

        $allowedScopes = array_column($this->scopeOptions(), 'id');

        if (! in_array($this->scope, $allowedScopes, true)) {
            $this->scope = $this->initialScope();
        }

        $this->areaId = $this->initialAreaId();
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function scopeOptions(): array
    {
        $options = [
            ['id' => 'mine', 'label' => __('Mis tareas')],
            ['id' => 'area', 'label' => __('Mi área')],
        ];

        $user = auth()->user();

        if ($user instanceof User && ($user->hasRole('super_admin') || $user->can('tickets.viewAny'))) {
            $options[] = ['id' => 'all', 'label' => __('Todas las áreas')];
        }

        return $options;
    }

    /**
     * Prefer the broadest scope the authenticated user is allowed to see.
     */
    private function initialScope(): string
    {
        $user = auth()->user();

        if ($user instanceof User && ($user->hasRole('super_admin') || $user->can('tickets.viewAny'))) {
            return 'all';
        }

        if ($user instanceof User && $user->managedAreas()->exists()) {
            return 'area';
        }

        return 'mine';
    }

    /**
     * Prefer the user's area, then the area with tasks, so assigned tickets are visible.
     */
    protected function initialAreaId(): ?int
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
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        $query = Task::query()->with([
            'area',
            'ticket',
            'assignee',
            'parent',
            'dependencies.dependsOnTask',
            'statusTransitions' => fn ($query) => $query
                ->orderByDesc('occurred_at')
                ->orderByDesc('id'),
        ]);

        if ($this->scope === 'mine') {
            if ($this->areaId === null) {
                return collect();
            }

            $query->where('assignee_id', $user->id)
                ->whereIn('area_id', $this->areas()->pluck('id'));
        } elseif ($this->scope === 'area') {
            if ($this->areaId === null) {
                return collect();
            }

            $query->where('area_id', $this->areaId);
        } elseif ($this->scope === 'all') {
            $query->whereIn('area_id', $this->areas()->pluck('id'));
        } else {
            return collect();
        }

        return $query->orderBy('position')->orderBy('id')->get();
    }

    /**
     * @return array<int, array{id: string, label: string, status: TaskStatus, tasks: Collection<int, Task>}>
     */
    #[Computed]
    public function columns(): array
    {
        return collect(TaskBoardColumn::cases())->map(function (TaskBoardColumn $column): array {
            $status = match ($column) {
                TaskBoardColumn::Todo => TaskStatus::Todo,
                TaskBoardColumn::InProgress => TaskStatus::InProgress,
                TaskBoardColumn::Done => TaskStatus::Done,
            };

            return [
                'id' => $column->value,
                'label' => $column->label(),
                'status' => $status,
                'tasks' => $this->tasks()
                    ->filter(fn (Task $task): bool => $task->status->kanbanColumn() === $column)
                    ->values(),
            ];
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
     * @return Collection<int, User>
     */
    #[Computed]
    public function editAssignees(): Collection
    {
        if ($this->editAreaId === null) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where('area_id', $this->editAreaId)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function availableTasks(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User || $this->areaId === null) {
            return collect();
        }

        $query = Task::query();

        if ($this->scope === 'mine') {
            $query->where('area_id', $this->areaId)
                ->where('assignee_id', $user->id);
        } elseif ($this->scope === 'area') {
            $query->where('area_id', $this->areaId);
        } elseif ($this->scope === 'all') {
            $query->whereIn('area_id', $this->areas()->pluck('id'));
        } else {
            return collect();
        }

        return $query->orderBy('title')->get();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function editAvailableTasks(): Collection
    {
        if ($this->editAreaId === null) {
            return collect();
        }

        $query = Task::query()->where('area_id', $this->editAreaId);
        $user = auth()->user();

        if ($user instanceof User
            && ! $user->hasRole('super_admin')
            && ! $user->can('tickets.viewAny')
            && ! $user->managedAreas()->whereKey($this->editAreaId)->exists()) {
            $query->where('assignee_id', $user->id);
        }

        return $query->orderBy('title')->get();
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
                'area',
                'ticket',
                'assignee',
                'parent',
                'children',
                'dependencies.dependsOnTask',
                'statusTransitions' => fn ($query) => $query
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id'),
                'statusTransitions.changedBy',
                'attachments',
                'comments.user',
                'comments.attachments',
            ])
            ->find($this->detailsTaskId);
    }

    /**
     * Latest transition that crossed a Kanban column boundary.
     */
    public function latestColumnMovement(Task $task): ?TaskStatusTransition
    {
        return $task->statusTransitions
            ->first(fn (TaskStatusTransition $transition): bool => $transition->changedColumn());
    }

    public function updatedAreaId(): void
    {
        $this->closeModals();
    }

    public function updatedScope(): void
    {
        $this->closeModals();
    }

    public function canChooseAssignee(): bool
    {
        return Gate::allows('create', Task::class);
    }

    public function canReassign(Task $task): bool
    {
        return Gate::allows('reassign', $task);
    }

    public function canReassignEditedTask(): bool
    {
        if ($this->editTaskId === null) {
            return false;
        }

        $task = Task::find($this->editTaskId);

        return $task instanceof Task && $this->canReassign($task);
    }

    public function canCommentOn(Task $task): bool
    {
        return Gate::allows('view', $task) && Gate::allows('create', Comment::class);
    }

    public function openCreate(string $column): void
    {
        Gate::authorize('create', Task::class);

        $this->resetCreateForm();
        $targetColumn = collect($this->columns())->firstWhere('id', $column);
        $this->targetStatus = is_array($targetColumn)
            ? $targetColumn['status']->value
            : TaskStatus::Todo->value;
        $this->showDetailsModal = false;
        $this->showCreateModal = true;
    }

    public function openSubtask(int $taskId): void
    {
        $parent = Task::findOrFail($taskId);
        Gate::authorize('createSubtask', $parent);

        $this->resetCreateForm();
        $this->parentTaskId = $parent->id;
        $this->areaId = $parent->area_id ?? $this->areaId;
        $this->targetStatus = TaskStatus::Todo->value;
        $user = auth()->user();
        $this->assigneeId = Gate::allows('create', Task::class)
            ? $parent->assignee_id
            : ($user instanceof User ? $user->id : null);
        $this->showDetailsModal = false;
        $this->showCreateModal = true;
    }

    public function createTask(CreateTask $action): void
    {
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
            'targetStatus' => ['required', Rule::in(['todo', 'in_progress', 'done'])],
            'areaId' => ['required', 'integer', 'exists:areas,id'],
        ]);

        if ($validated['parentTaskId'] !== null) {
            Gate::authorize('createSubtask', Task::findOrFail($validated['parentTaskId']));
        } else {
            Gate::authorize('create', Task::class);
        }

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

        $this->resetCommentForm();
        $this->detailsTaskId = $task->id;
        $this->showDetailsModal = true;
    }

    /**
     * Add a comment with optional private files and refresh the detail modal.
     */
    public function addComment(AddTaskComment $action): void
    {
        if ($this->detailsTaskId === null) {
            return;
        }

        $task = Task::findOrFail($this->detailsTaskId);

        $validated = $this->validate([
            'commentBody' => ['nullable', 'string', 'max:10000'],
            'commentIsInternal' => ['boolean'],
            'commentFiles' => ['nullable', 'array', 'max:5'],
            'commentFiles.*' => [
                'file',
                'max:'.(int) config('tickets.attachments.max_kb', 10240),
                'mimetypes:'.implode(',', array_keys((array) config('tickets.attachments.allowed_mimes', []))),
            ],
        ]);

        try {
            $action->handle(
                $task,
                $validated['commentBody'] ?? '',
                (bool) ($validated['commentIsInternal'] ?? false),
                $validated['commentFiles'] ?? [],
            );
        } catch (ValidationException $exception) {
            $this->reportValidationError($exception);

            return;
        }

        unset($this->detailsTask);
        $this->resetCommentForm();
        Flux::toast(text: __('Comentario registrado.'), variant: 'success');
    }

    public function openEdit(int $taskId): void
    {
        $task = Task::findOrFail($taskId);
        Gate::authorize('update', $task);

        $this->editTaskId = $task->id;
        $this->editAreaId = $task->area_id;
        $this->editTitle = $task->title;
        $this->editDescription = $task->description;
        $this->editPriority = $task->priority->value;
        $this->editDueAt = $task->due_at?->format('Y-m-d');
        $this->editEstimatedMinutes = $task->estimated_minutes;
        $this->editAssigneeId = $task->assignee_id;
        $this->editParentTaskId = $task->parent_id;
        $this->editDependsOnTaskId = $task->dependencies
            ->firstWhere('type', 'finish_to_start')?->depends_on_task_id;
        $this->showDetailsModal = false;
        $this->showCreateModal = false;
        $this->showEditModal = true;
    }

    public function updateTask(UpdateTask $action): void
    {
        if ($this->editTaskId === null) {
            return;
        }

        $task = Task::findOrFail($this->editTaskId);

        $validated = $this->validate([
            'editTitle' => ['required', 'string', 'max:255'],
            'editDescription' => ['nullable', 'string'],
            'editPriority' => ['required', Rule::in(array_map(
                fn (TicketPriority $case): string => $case->value,
                TicketPriority::cases(),
            ))],
            'editDueAt' => ['nullable', 'date'],
            'editEstimatedMinutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'editAssigneeId' => ['nullable', 'integer', 'exists:users,id'],
            'editParentTaskId' => ['nullable', 'integer', 'exists:tasks,id'],
            'editDependsOnTaskId' => ['nullable', 'integer', 'exists:tasks,id'],
        ]);

        try {
            $action->handle($task, [
                'title' => $validated['editTitle'],
                'description' => $validated['editDescription'] ?: null,
                'priority' => $validated['editPriority'],
                'due_at' => $validated['editDueAt'],
                'estimated_minutes' => $validated['editEstimatedMinutes'],
                'assignee_id' => $validated['editAssigneeId'],
                'parent_id' => $validated['editParentTaskId'],
                'depends_on_task_id' => $validated['editDependsOnTaskId'],
            ]);
        } catch (ValidationException $exception) {
            $this->reportValidationError($exception);

            return;
        }

        unset($this->detailsTask, $this->tasks, $this->editAvailableTasks, $this->editAssignees);
        $this->closeModals();
        Flux::toast(text: __('Tarea actualizada.'), variant: 'success');
    }

    public function handleSort(int $taskId, int $position, string $column): void
    {
        $targetColumn = collect($this->columns())->firstWhere('id', $column);
        $status = is_array($targetColumn) ? $targetColumn['status'] : null;

        if (! $status instanceof TaskStatus) {
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
        Flux::modal('edit-task')->close();

        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->detailsTaskId = null;
        $this->showDetailsModal = false;
        $this->resetCommentForm();
    }

    private function resetCommentForm(): void
    {
        $this->commentBody = '';
        $this->commentIsInternal = false;
        $this->commentFiles = [];
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
