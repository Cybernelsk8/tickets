<?php

namespace App\Actions\Reports;

use App\Models\Area;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildDashboardReport
{
    /**
     * Build every aggregate shown on the dashboard for the given user.
     *
     * Root tasks are the deliverables that count as completed work. Subtasks
     * only report operational progress and never close a deliverable.
     *
     * @param  array{from?: string|null, to?: string|null, area_id?: int|string|null, category_id?: int|string|null, assignee_id?: int|string|null}  $filters
     * @return array{
     *     scopeLabel: string,
     *     filters: array{from: string, to: string, area_id: int|null, category_id: int|null, assignee_id: int|null},
     *     totalTickets: int,
     *     openTickets: int,
     *     totalDeliverables: int,
     *     completedDeliverables: int,
     *     deliverableCompletionRate: float,
     *     overdueDeliverables: int,
     *     totalSubtasks: int,
     *     completedSubtasks: int,
     *     subtaskProgressRate: float,
     *     readyForReview: int,
     *     averageCycleHours: float,
     *     statusSeries: array<int, array{label: string, value: int}>,
     *     areaSeries: array<int, array{label: string, value: int}>,
     *     prioritySeries: array<int, array{label: string, value: int}>,
     *     categorySeries: array<int, array{label: string, value: int}>,
     *     weeklySeries: array{categories: array<int, string>, values: array<int, int>},
     *     workloadSeries: array<int, array{label: string, active: int, completed: int}>,
     *     stageAverages: array<int, array{label: string, hours: float}>,
     *     recentWorkItems: Collection<int, DashboardWorkItem>,
     *     readyForReviewWorkItems: Collection<int, DashboardWorkItem>
     * }
     */
    public function handle(User $user, array $filters = []): array
    {
        [$from, $to] = $this->resolveDates($filters);
        $personalScope = $this->isPersonalScope($user);
        $visibleAreaIds = $this->visibleAreaIds($user);
        $areaIds = $this->resolveAreaIds($visibleAreaIds, $filters);
        $categoryId = $this->resolveCategoryId($filters);
        $assigneeId = $personalScope ? null : $this->resolveAssigneeId($user, $visibleAreaIds, $filters);

        $ticketQuery = Ticket::query()
            ->whereIn('area_id', $areaIds)
            ->whereBetween('created_at', [$from, $to]);
        $this->applyTicketFilters($ticketQuery, $areaIds, $categoryId, $assigneeId, $personalScope, $user);

        $taskQuery = Task::query()
            ->whereIn('area_id', $areaIds)
            ->whereBetween('created_at', [$from, $to]);
        $this->applyTaskFilters($taskQuery, $categoryId, $assigneeId, $personalScope, $user);

        /** @var Builder<Task> $deliverableQuery */
        $deliverableQuery = (clone $taskQuery)->roots();

        /** @var Builder<Task> $subtaskQuery */
        $subtaskQuery = (clone $taskQuery)->subtasks();

        /** @var Builder<Task> $readyForReviewQuery */
        $readyForReviewQuery = (clone $deliverableQuery)->readyForReview();

        $totalTickets = (clone $ticketQuery)->count();
        $openTickets = (clone $ticketQuery)
            ->whereNot('status', TaskStatus::Done->value)
            ->count();
        $totalDeliverables = (clone $deliverableQuery)->count();
        $completedDeliverables = (clone $deliverableQuery)
            ->where('status', TaskStatus::Done->value)
            ->count();
        $overdueDeliverables = (clone $deliverableQuery)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNot('status', TaskStatus::Done->value)
            ->count();
        $totalSubtasks = (clone $subtaskQuery)->count();
        $completedSubtasks = (clone $subtaskQuery)
            ->where('status', TaskStatus::Done->value)
            ->count();
        $readyForReview = (clone $readyForReviewQuery)->count();

        $statusRows = (clone $deliverableQuery)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $areaRows = (clone $deliverableQuery)
            ->select('area_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('area_id')
            ->pluck('total', 'area_id');
        $priorityRows = (clone $ticketQuery)
            ->select('priority')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');
        $categoryRows = (clone $ticketQuery)
            ->select('category_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        /** @var Collection<int|string, int|string> $activeWorkloadRows */
        $activeWorkloadRows = (clone $taskQuery)
            ->whereNot('status', TaskStatus::Done->value)
            ->select('assignee_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id');

        /** @var Collection<int|string, int|string> $completedWorkloadRows */
        $completedWorkloadRows = (clone $taskQuery)
            ->where('status', TaskStatus::Done->value)
            ->select('assignee_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id');

        /** @var Collection<int|string, array{active: int, completed: int}> $workloadRows */
        $workloadRows = $activeWorkloadRows
            ->map(fn (int|string $total, int|string $assigneeId): array => [
                'active' => (int) $total,
                'completed' => (int) ($completedWorkloadRows[$assigneeId] ?? 0),
            ])
            ->union($completedWorkloadRows->map(fn (int|string $total): array => [
                'active' => 0,
                'completed' => (int) $total,
            ]));

        $areaNames = Area::query()
            ->whereIn('id', $areaRows->keys())
            ->pluck('name', 'id');
        $categoryNames = TicketCategory::query()
            ->whereIn('id', $categoryRows->keys())
            ->pluck('name', 'id');
        $workloadUserIds = $workloadRows->keys()
            ->filter(fn (int|string $id): bool => $id !== '')
            ->all();
        $userNames = User::query()
            ->whereIn('id', $workloadUserIds)
            ->pluck('name', 'id');

        $stageRows = (clone $deliverableQuery)
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, started_at, testing_at)) as to_testing')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, testing_at, completed_at)) as to_done')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, started_at, completed_at)) as total_cycle')
            ->first();
        $stageMetrics = $stageRows instanceof Task ? $stageRows->getAttributes() : [];

        $weeklyRows = (clone $deliverableQuery)
            ->selectRaw("DATE_FORMAT(created_at, '%x-W%v') as week, COUNT(*) as total")
            ->groupBy('week')
            ->pluck('total', 'week');

        $recentTasks = (clone $taskQuery)
            ->with($this->workItemRelations())
            ->with($this->rootTaskRelations())
            ->withCount($this->subtaskCounts())
            ->orderByRaw('COALESCE(completed_at, updated_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $recentTickets = (clone $ticketQuery)
            ->with([
                'area:id,name',
                'category:id,name',
                'assignee:id,name',
                'creator:id,name',
                'rootTask' => fn (Builder|Relation $query): mixed => $query
                    ->with($this->workItemRelations(false))
                    ->withCount($this->subtaskCounts()),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $readyForReviewTasks = (clone $readyForReviewQuery)
            ->with($this->workItemRelations())
            ->withCount($this->subtaskCounts())
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $recentWorkItems = $recentTasks
            ->map(function (Task $task): DashboardWorkItem {
                $root = $task->parent instanceof Task ? $task->parent : $task;

                return DashboardWorkItem::fromTask($root, null, $task);
            })
            ->concat($recentTickets->map(function (Ticket $ticket): DashboardWorkItem {
                $root = $ticket->rootTask;

                if (! $root instanceof Task) {
                    return DashboardWorkItem::fromTicket($ticket);
                }

                $root->setRelation('ticket', $ticket);

                return DashboardWorkItem::fromTask($root, $ticket);
            }))
            ->unique(fn (DashboardWorkItem $item): string => $item->key)
            ->sortByDesc(fn (DashboardWorkItem $item): int => $item->activityTimestamp)
            ->values()
            ->take(10);

        $readyForReviewWorkItems = $readyForReviewTasks
            ->map(fn (Task $task): DashboardWorkItem => DashboardWorkItem::fromTask($task));

        return [
            'scopeLabel' => $this->scopeLabel($user, $personalScope),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'area_id' => $areaIds->count() === 1 ? (int) $areaIds->first() : null,
                'category_id' => $categoryId,
                'assignee_id' => $personalScope ? $user->id : $assigneeId,
            ],
            'totalTickets' => $totalTickets,
            'openTickets' => $openTickets,
            'totalDeliverables' => $totalDeliverables,
            'completedDeliverables' => $completedDeliverables,
            'deliverableCompletionRate' => $totalDeliverables > 0
                ? round($completedDeliverables / $totalDeliverables * 100, 1)
                : 0.0,
            'overdueDeliverables' => $overdueDeliverables,
            'totalSubtasks' => $totalSubtasks,
            'completedSubtasks' => $completedSubtasks,
            'subtaskProgressRate' => $totalSubtasks > 0
                ? round($completedSubtasks / $totalSubtasks * 100, 1)
                : 0.0,
            'readyForReview' => $readyForReview,
            'averageCycleHours' => round((float) ($stageMetrics['total_cycle'] ?? 0), 1),
            'statusSeries' => collect(TaskStatus::cases())->map(fn (TaskStatus $status): array => [
                'label' => $status->label(),
                'value' => (int) ($statusRows[$status->value] ?? 0),
            ])->all(),
            'areaSeries' => $areaRows
                ->map(fn (int|string $total, int|string $areaId): array => [
                    'label' => (string) ($areaNames[$areaId] ?? __('Sin área')),
                    'value' => (int) $total,
                ])
                ->values()
                ->all(),
            'prioritySeries' => collect(TicketPriority::cases())->map(fn (TicketPriority $priority): array => [
                'label' => $priority->label(),
                'value' => (int) ($priorityRows[$priority->value] ?? 0),
            ])->all(),
            'categorySeries' => $categoryRows
                ->map(fn (int|string $total, int|string $categoryId): array => [
                    'label' => (string) ($categoryNames[$categoryId] ?? __('Sin categoría')),
                    'value' => (int) $total,
                ])
                ->values()
                ->all(),
            'weeklySeries' => $this->weeklySeries($from, $to, $weeklyRows),
            'workloadSeries' => $workloadRows
                ->map(fn (array $counts, int|string $assigneeId): array => [
                    'label' => $assigneeId === ''
                        ? __('Sin responsable')
                        : (string) ($userNames[$assigneeId] ?? __('Sin responsable')),
                    'active' => $counts['active'],
                    'completed' => $counts['completed'],
                ])
                ->values()
                ->all(),
            'stageAverages' => [
                ['label' => __('Inicio → Pruebas'), 'hours' => round((float) ($stageMetrics['to_testing'] ?? 0), 1)],
                ['label' => __('Pruebas → Terminado'), 'hours' => round((float) ($stageMetrics['to_done'] ?? 0), 1)],
                ['label' => __('Ciclo total'), 'hours' => round((float) ($stageMetrics['total_cycle'] ?? 0), 1)],
            ],
            'recentWorkItems' => $recentWorkItems,
            'readyForReviewWorkItems' => $readyForReviewWorkItems,
        ];
    }

    /**
     * Areas the user may include in a dashboard report.
     *
     * @return Collection<int, Area>
     */
    public function availableAreas(User $user): Collection
    {
        if ($user->hasRole('super_admin') || $user->can('reports.view')) {
            return Area::query()->where('is_active', true)->orderBy('name')->get();
        }

        if ($user->can('reports.viewOwnArea')) {
            $managedAreaIds = $user->managedAreas()
                ->where('is_active', true)
                ->pluck('areas.id');

            if ($managedAreaIds->isNotEmpty()) {
                return Area::query()->whereIn('id', $managedAreaIds)->orderBy('name')->get();
            }
        }

        if ($user->area_id === null) {
            return collect();
        }

        return Area::query()->whereKey($user->area_id)->where('is_active', true)->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function visibleAreaIds(User $user): Collection
    {
        return $this->availableAreas($user)->pluck('id');
    }

    private function isPersonalScope(User $user): bool
    {
        return ! $user->hasRole('super_admin')
            && ! $user->can('reports.view')
            && ! $user->can('reports.viewOwnArea');
    }

    private function scopeLabel(User $user, bool $personalScope): string
    {
        if (! $personalScope && ($user->hasRole('super_admin') || $user->can('reports.view'))) {
            return __('Todas las áreas');
        }

        if (! $personalScope && $user->can('reports.viewOwnArea')) {
            return __('Áreas gestionadas');
        }

        return __('Mis datos');
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array{CarbonInterface, CarbonInterface}
     */
    private function resolveDates(array $filters): array
    {
        $from = $this->parseDate($filters['from'] ?? null) ?? now()->subDays(30)->startOfDay();
        $to = $this->parseDate($filters['to'] ?? null) ?? now()->endOfDay();

        if ($from->gt($to)) {
            return [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function parseDate(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date instanceof Carbon && $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * @param  Collection<int, int>  $visibleAreaIds
     * @param  array{area_id?: int|string|null}  $filters
     * @return Collection<int, int>
     */
    private function resolveAreaIds(Collection $visibleAreaIds, array $filters): Collection
    {
        $requestedAreaId = $this->positiveInt($filters['area_id'] ?? null);

        if ($requestedAreaId === null || ! $visibleAreaIds->contains($requestedAreaId)) {
            return $visibleAreaIds;
        }

        return $visibleAreaIds->filter(fn (int $areaId): bool => $areaId === $requestedAreaId)->values();
    }

    /**
     * @param  array{category_id?: int|string|null}  $filters
     */
    private function resolveCategoryId(array $filters): ?int
    {
        $categoryId = $this->positiveInt($filters['category_id'] ?? null);

        if ($categoryId === null) {
            return null;
        }

        return TicketCategory::query()
            ->where('is_active', true)
            ->whereKey($categoryId)
            ->exists() ? $categoryId : null;
    }

    /**
     * @param  Collection<int, int>  $visibleAreaIds
     * @param  array{assignee_id?: int|string|null}  $filters
     */
    private function resolveAssigneeId(User $user, Collection $visibleAreaIds, array $filters): ?int
    {
        $assigneeId = $this->positiveInt($filters['assignee_id'] ?? null);

        if ($assigneeId === null) {
            return null;
        }

        return User::query()
            ->where('is_active', true)
            ->whereIn('area_id', $visibleAreaIds)
            ->whereKey($assigneeId)
            ->exists() ? $assigneeId : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  Collection<int, int>  $areaIds
     */
    private function applyTicketFilters(
        Builder $query,
        Collection $areaIds,
        ?int $categoryId,
        ?int $assigneeId,
        bool $personalScope,
        User $user,
    ): void {
        $query->whereIn('area_id', $areaIds);

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($personalScope) {
            $query->where('assignee_id', $user->id);
        } elseif ($assigneeId !== null) {
            $query->where('assignee_id', $assigneeId);
        }
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyTaskFilters(
        Builder $query,
        ?int $categoryId,
        ?int $assigneeId,
        bool $personalScope,
        User $user,
    ): void {
        if ($categoryId !== null) {
            $query->whereHas('ticket', fn (Builder $ticketQuery): mixed => $ticketQuery
                ->where('category_id', $categoryId));
        }

        if ($personalScope) {
            $query->where('assignee_id', $user->id);
        } elseif ($assigneeId !== null) {
            $query->where('assignee_id', $assigneeId);
        }
    }

    /**
     * Relations required to render a work-item row without N+1 queries.
     *
     * @return array<int, string>
     */
    private function workItemRelations(bool $includeTicket = true): array
    {
        $relations = [
            'area:id,name',
            'assignee:id,name',
            'creator:id,name',
            'latestStatusTransition.changedBy:id,name',
        ];

        if ($includeTicket) {
            $relations[] = 'ticket:id,code,subject,description,area_id,category_id,priority,status,assignee_id,requester_name,requester_email,created_by,assigned_at,closed_at,created_at';
            $relations[] = 'ticket.category:id,name';
        }

        return $relations;
    }

    /**
     * @return array<string, \Closure>
     */
    private function rootTaskRelations(): array
    {
        return [
            'parent' => fn (Builder|Relation $query): mixed => $query
                ->with($this->workItemRelations())
                ->withCount($this->subtaskCounts()),
        ];
    }

    /**
     * @return array<int|string, \Closure|string>
     */
    private function subtaskCounts(): array
    {
        return [
            'children as subtasks_total_count',
            'children as subtasks_done_count' => fn (Builder|Relation $query): mixed => $query
                ->where('status', TaskStatus::Done->value),
        ];
    }

    /**
     * @param  Collection<int|string, int|string>  $weeklyRows
     * @return array{categories: array<int, string>, values: array<int, int>}
     */
    private function weeklySeries(CarbonInterface $from, CarbonInterface $to, Collection $weeklyRows): array
    {
        $categories = [];
        $values = [];
        $cursor = $from->copy()->startOfWeek(Carbon::MONDAY);
        $lastWeek = $to->copy()->startOfWeek(Carbon::MONDAY);

        while ($cursor->lessThanOrEqualTo($lastWeek)) {
            $categories[] = $cursor->copy()->locale('es')->isoFormat('MMM D');
            $values[] = (int) ($weeklyRows[$cursor->isoFormat('GGGG-[W]WW')] ?? 0);
            $cursor = $cursor->addWeek();
        }

        return ['categories' => $categories, 'values' => $values];
    }
}
