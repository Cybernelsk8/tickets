<?php

namespace App\Livewire\Boards;

use App\Actions\Tasks\AddTaskComment;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\MoveTask;
use App\Actions\Tasks\UpdateTask;
use App\Models\Area;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Task;
use App\Models\TaskStatusTransition;
use App\Models\User;
use App\TaskBoardColumn;
use App\TaskStatus;
use App\TicketPriority;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
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
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    private const ACTIVE_COLUMN_LIMIT = 50;

    private const DONE_COLUMN_LIMIT = 20;

    private const LOAD_MORE_STEP = 50;

    private const RECENT_DONE_DAYS = 7;

    private const HISTORY_PER_PAGE = 20;

    private const LIST_PER_PAGE = 20;

    #[Url(except: '')]
    public string $scope = '';

    public ?int $areaId = null;

    public string $search = '';

    public ?int $filterAssigneeId = null;

    public string $filterPriority = '';

    public string $filterDue = '';

    public string $viewMode = 'board';

    /** @var array<string, int> */
    public array $columnLimits = [];

    public string $openHistoryGroup = '';

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

    public ?int $expandedTaskId = null;

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
     * Build the shared query used by the board, the list and the history.
     *
     * @return Builder<Task>|null
     */
    private function scopedTasksQuery(): ?Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $query = Task::query();

        if ($this->scope === 'mine') {
            if ($this->areaId === null) {
                return null;
            }

            $query->where('assignee_id', $user->id)
                ->whereIn('area_id', $this->areas()->pluck('id'));
        } elseif ($this->scope === 'area') {
            if ($this->areaId === null) {
                return null;
            }

            $query->where('area_id', $this->areaId);
        } elseif ($this->scope === 'all') {
            $query->whereIn('area_id', $this->areas()->pluck('id'));
        } else {
            return null;
        }

        if ($this->search !== '') {
            $term = '%'.mb_strtolower($this->search).'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->whereRaw('LOWER(tasks.title) LIKE ?', [$term])
                    ->orWhereHas('ticket', function (Builder $ticketQuery) use ($term): void {
                        $ticketQuery->whereRaw('LOWER(tickets.code) LIKE ?', [$term]);
                    })
                    ->orWhereHas('assignee', function (Builder $assigneeQuery) use ($term): void {
                        $assigneeQuery->whereRaw('LOWER(users.name) LIKE ?', [$term]);
                    });
            });
        }

        if ($this->filterAssigneeId !== null) {
            $query->where('assignee_id', $this->filterAssigneeId);
        }

        $priorities = array_map(
            fn (TicketPriority $case): string => $case->value,
            TicketPriority::cases(),
        );

        if (in_array($this->filterPriority, $priorities, true)) {
            $query->where('priority', $this->filterPriority);
        }

        if ($this->filterDue === 'overdue') {
            $query->whereNotNull('due_at')->where('due_at', '<', now());
        } elseif ($this->filterDue === 'week') {
            $query->whereNotNull('due_at')->whereBetween('due_at', [now(), now()->addWeek()]);
        }

        return $query;
    }

    /**
     * Relations needed by a collapsed or expanded card.
     *
     * @return array<int|string, mixed>
     */
    private function taskCardRelations(): array
    {
        return [
            'area:id,name,color',
            'ticket:id,code',
            'assignee:id,name',
            'parent:id,title',
            'dependencies.dependsOnTask:id,title,status',
            'statusTransitions' => fn ($query): mixed => $query
                ->select('id', 'task_id', 'from_status', 'to_status', 'occurred_at')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id'),
        ];
    }

    /**
     * @return Collection<int, Task>
     */
    private function boardTasks(TaskBoardColumn $column): Collection
    {
        $query = $this->scopedTasksQuery();

        if ($query === null) {
            return collect();
        }

        $query->with($this->taskCardRelations());

        if ($column === TaskBoardColumn::Done) {
            $cutoff = now()->subDays(self::RECENT_DONE_DAYS);

            return $query->where('status', TaskStatus::Done->value)
                ->where(function (Builder $query) use ($cutoff): void {
                    $query->where('completed_at', '>=', $cutoff)
                        ->orWhereNull('completed_at');
                })
                ->orderByDesc('completed_at')
                ->orderByDesc('id')
                ->limit($this->columnLimits['done'] ?? self::DONE_COLUMN_LIMIT)
                ->get();
        }

        $statuses = collect(TaskStatus::cases())
            ->filter(fn (TaskStatus $status): bool => $status->kanbanColumn() === $column)
            ->map(fn (TaskStatus $status): string => $status->value)
            ->all();

        return $query->whereIn('status', $statuses)
            ->orderBy('position')
            ->orderBy('id')
            ->limit($this->columnLimits[$column->value] ?? self::ACTIVE_COLUMN_LIMIT)
            ->get();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function columnCounts(): array
    {
        $counts = array_fill_keys(
            array_map(fn (TaskBoardColumn $column): string => $column->value, TaskBoardColumn::cases()),
            0,
        );

        $query = $this->scopedTasksQuery();

        if ($query === null) {
            return $counts;
        }

        $rows = (clone $query)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        foreach ($rows as $statusValue => $total) {
            $status = TaskStatus::tryFrom((string) $statusValue);

            if (! $status instanceof TaskStatus) {
                continue;
            }

            $counts[$status->kanbanColumn()->value] += (int) $total;
        }

        return $counts;
    }

    /**
     * @return array<int, array{id: string, label: string, status: TaskStatus, tasks: Collection<int, Task>, total: int, hasMore: bool}>
     */
    #[Computed]
    public function columns(): array
    {
        $counts = $this->columnCounts();

        return collect(TaskBoardColumn::cases())->map(function (TaskBoardColumn $column) use ($counts): array {
            $status = match ($column) {
                TaskBoardColumn::Todo => TaskStatus::Todo,
                TaskBoardColumn::InProgress => TaskStatus::InProgress,
                TaskBoardColumn::Done => TaskStatus::Done,
            };
            $tasks = $this->boardTasks($column);
            $total = $counts[$column->value] ?? 0;

            return [
                'id' => $column->value,
                'label' => $column->label(),
                'status' => $status,
                'tasks' => $tasks,
                'total' => $total,
                'hasMore' => $total > $tasks->count(),
            ];
        })->all();
    }

    /**
     * @return array<int, array{month: string, label: string, total: int}>
     */
    #[Computed]
    public function historyGroups(): array
    {
        $query = $this->scopedTasksQuery();

        if ($query === null) {
            return [];
        }

        return (clone $query)
            ->where('status', TaskStatus::Done->value)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', now()->subDays(self::RECENT_DONE_DAYS))
            ->selectRaw("DATE_FORMAT(completed_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderByDesc('month')
            ->pluck('total', 'month')
            ->map(function (int|string $total, string $month): array {
                return [
                    'month' => $month,
                    'label' => $this->historyMonthLabel($month),
                    'total' => (int) $total,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return CursorPaginator<int, Task>
     */
    public function historyTasks(string $month): CursorPaginator
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Task::query()->whereRaw('1 = 0')->orderBy('id')->cursorPaginate(self::HISTORY_PER_PAGE);
        }

        $query = $this->scopedTasksQuery();

        if ($query === null) {
            return Task::query()->whereRaw('1 = 0')->orderBy('id')->cursorPaginate(self::HISTORY_PER_PAGE);
        }

        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $query->where('status', TaskStatus::Done->value)
            ->whereBetween('completed_at', [$start, $end])
            ->with($this->taskCardRelations())
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::HISTORY_PER_PAGE);
    }

    /**
     * @return LengthAwarePaginator<int, Task>
     */
    #[Computed]
    public function listTasks(): LengthAwarePaginator
    {
        $query = $this->scopedTasksQuery();

        if ($query === null) {
            return Task::query()->whereRaw('1 = 0')->paginate(self::LIST_PER_PAGE);
        }

        return $query->with($this->taskCardRelations())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::LIST_PER_PAGE);
    }

    private function historyMonthLabel(string $month): string
    {
        $date = Carbon::createFromFormat('!Y-m', $month);

        return $date instanceof Carbon
            ? $date->locale('es')->translatedFormat('F Y')
            : $month;
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
    public function filterAssignees(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('area_id', $this->areas()->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function dueFilterOptions(): array
    {
        return [
            ['id' => '', 'label' => __('Cualquier fecha')],
            ['id' => 'overdue', 'label' => __('Vencidas')],
            ['id' => 'week', 'label' => __('Vencen en 7 días')],
        ];
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

    public function toggleCard(int $taskId): void
    {
        $task = Task::findOrFail($taskId);
        Gate::authorize('view', $task);

        $this->expandedTaskId = $this->expandedTaskId === $taskId ? null : $taskId;
    }

    /**
     * Group attachments by file type so each type is rendered once.
     *
     * @param  Collection<int, Attachment>  $attachments
     * @return array<int, array{type: string, icon: string, color: string, label: string, tooltip: string, files: Collection<int, Attachment>}>
     */
    public function attachmentGroups(Collection $attachments): array
    {
        return $attachments
            ->groupBy(fn (Attachment $attachment): string => $this->attachmentType($attachment))
            ->map(function (Collection $files, string $type): array {
                return [
                    'type' => $type,
                    'icon' => $this->attachmentIcon($type),
                    'color' => $this->attachmentColor($type),
                    'label' => $this->attachmentTypeLabel($type),
                    'tooltip' => $files->pluck('original_name')->implode(', '),
                    'files' => $files->values(),
                ];
            })
            ->values()
            ->all();
    }

    private function attachmentType(Attachment $attachment): string
    {
        $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
        $mime = strtolower($attachment->mime_type ?? '');

        return match (true) {
            str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true) => 'image',
            $extension === 'pdf' || $mime === 'application/pdf' => 'pdf',
            in_array($extension, ['xls', 'xlsx', 'csv', 'ods'], true)
                || str_contains($mime, 'spreadsheet')
                || str_contains($mime, 'excel')
                || $mime === 'text/csv' => 'spreadsheet',
            in_array($extension, ['doc', 'docx', 'odt', 'rtf'], true)
                || str_contains($mime, 'word')
                || str_contains($mime, 'opendocument.text') => 'document',
            $extension === 'txt' || str_starts_with($mime, 'text/') => 'text',
            default => 'other',
        };
    }

    private function attachmentIcon(string $type): string
    {
        return match ($type) {
            'image' => 'file-image',
            'pdf', 'document', 'text' => 'file-text',
            'spreadsheet' => 'file-spreadsheet',
            default => 'paperclip',
        };
    }

    private function attachmentColor(string $type): string
    {
        return match ($type) {
            'image' => 'text-violet-500',
            'pdf' => 'text-rose-500',
            'spreadsheet' => 'text-emerald-500',
            'document' => 'text-blue-500',
            'text' => 'text-zinc-500',
            default => 'text-amber-500',
        };
    }

    private function attachmentTypeLabel(string $type): string
    {
        return match ($type) {
            'image' => __('Imágenes'),
            'pdf' => __('PDF'),
            'spreadsheet' => __('Hojas de cálculo'),
            'document' => __('Documentos'),
            'text' => __('Texto'),
            default => __('Otros archivos'),
        };
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
        $this->resetBoardState();
    }

    public function updatedScope(): void
    {
        $this->closeModals();
        $this->resetBoardState();
    }

    public function updatedSearch(): void
    {
        $this->resetBoardState();
    }

    public function updatedFilterAssigneeId(): void
    {
        $this->resetBoardState();
    }

    public function updatedFilterPriority(): void
    {
        $this->resetBoardState();
    }

    public function updatedFilterDue(): void
    {
        $this->resetBoardState();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['board', 'list'], true) ? $mode : 'board';
        $this->resetBoardState();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterAssigneeId', 'filterPriority', 'filterDue']);
        $this->resetBoardState();
    }

    public function loadMore(string $column): void
    {
        $boardColumn = TaskBoardColumn::tryFrom($column);

        if (! $boardColumn instanceof TaskBoardColumn) {
            return;
        }

        $currentLimit = $this->columnLimits[$boardColumn->value]
            ?? ($boardColumn === TaskBoardColumn::Done ? self::DONE_COLUMN_LIMIT : self::ACTIVE_COLUMN_LIMIT);
        $step = $boardColumn === TaskBoardColumn::Done ? self::DONE_COLUMN_LIMIT : self::LOAD_MORE_STEP;

        $this->columnLimits[$boardColumn->value] = $currentLimit + $step;

        unset($this->columns);
    }

    public function toggleHistoryGroup(string $month): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return;
        }

        $this->openHistoryGroup = $this->openHistoryGroup === $month ? '' : $month;
        $this->paginators['cursor'] = '';
    }

    public function canCreateTask(): bool
    {
        return Gate::allows('create', Task::class);
    }

    public function canChooseAssignee(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('tasks.assign');
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

    /**
     * Quick state buttons kept alongside drag and drop.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function quickTransitions(Task $task): array
    {
        if ($task->status !== TaskStatus::InProgress
            || ! $task->status->canTransitionTo(TaskStatus::Testing)
            || ! Gate::allows('move', $task)) {
            return [];
        }

        return [
            ['value' => TaskStatus::Testing->value, 'label' => TaskStatus::Testing->label()],
        ];
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

        if (! $this->canChooseAssignee()) {
            $user = auth()->user();
            $this->assigneeId = $user instanceof User ? $user->id : null;
            $this->targetStatus = TaskStatus::Todo->value;
        }

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
        $this->assigneeId = $this->canChooseAssignee()
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
            'assigneeId' => $this->canChooseAssignee()
                ? ['nullable', 'integer', 'exists:users,id']
                : ['required', 'integer', Rule::in([auth()->id()])],
            'priority' => ['required', Rule::in(array_map(
                fn (TicketPriority $case): string => $case->value,
                TicketPriority::cases(),
            ))],
            'estimatedMinutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'actualMinutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'dueAt' => ['nullable', 'date'],
            'dependsOnTaskId' => ['nullable', 'integer', 'exists:tasks,id'],
            'targetStatus' => $this->canChooseAssignee()
                ? ['required', Rule::in(['todo', 'in_progress', 'done'])]
                : ['required', Rule::in([TaskStatus::Todo->value])],
            'areaId' => $this->canChooseAssignee()
                ? ['required', 'integer', 'exists:areas,id']
                : ['required', 'integer', Rule::in([auth()->user()?->area_id])],
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

        unset($this->detailsTask, $this->columns, $this->columnCounts, $this->editAvailableTasks, $this->editAssignees);
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

            return;
        }

        unset($this->columns, $this->columnCounts, $this->historyGroups, $this->listTasks);
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
        $this->expandedTaskId = null;
        $this->resetCommentForm();

        unset($this->columns, $this->columnCounts, $this->historyGroups, $this->listTasks);
    }

    private function resetBoardState(): void
    {
        $this->columnLimits = [];
        $this->openHistoryGroup = '';
        $this->paginators['page'] = 1;
        $this->paginators['cursor'] = '';

        unset($this->columns, $this->columnCounts, $this->historyGroups, $this->listTasks);
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
