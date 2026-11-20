<?php

namespace App\Livewire\Dashboard;

use App\Actions\Reports\BuildDashboardReport;
use App\Models\Area;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $from = '';

    public string $to = '';

    public ?int $areaId = null;

    public ?int $categoryId = null;

    public ?int $assigneeId = null;

    public function mount(): void
    {
        $this->resetFilters();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function report(): array
    {
        return (new BuildDashboardReport)->handle($this->authenticatedUser(), [
            'from' => $this->from,
            'to' => $this->to,
            'area_id' => $this->areaId,
            'category_id' => $this->categoryId,
            'assignee_id' => $this->assigneeId,
        ]);
    }

    /**
     * @return Collection<int, Area>
     */
    #[Computed]
    public function areas(): Collection
    {
        return (new BuildDashboardReport)->availableAreas($this->authenticatedUser());
    }

    /**
     * @return Collection<int, TicketCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return TicketCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignees(): Collection
    {
        $areaIds = $this->areas()->pluck('id');

        if ($areaIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->whereIn('area_id', $areaIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array<string, array{key: string, series: array<int, mixed>, options: array<string, mixed>}>
     */
    #[Computed]
    public function charts(): array
    {
        $report = $this->report();
        $key = md5(json_encode($report['filters']) ?: '');

        return [
            'status' => [
                'key' => 'status-'.$key,
                'series' => array_column($report['statusSeries'], 'value'),
                'options' => [
                    'chart' => ['type' => 'donut', 'height' => 260, 'toolbar' => ['show' => false]],
                    'labels' => array_column($report['statusSeries'], 'label'),
                    'colors' => ['#71717a', '#0ea5e9', '#6366f1', '#f59e0b', '#22c55e', '#e11d48'],
                    'legend' => ['position' => 'bottom'],
                    'dataLabels' => ['enabled' => true],
                    'noData' => ['text' => __('Sin datos en el periodo')],
                ],
            ],
            'priority' => [
                'key' => 'priority-'.$key,
                'series' => array_column($report['prioritySeries'], 'value'),
                'options' => [
                    'chart' => ['type' => 'donut', 'height' => 260, 'toolbar' => ['show' => false]],
                    'labels' => array_column($report['prioritySeries'], 'label'),
                    'colors' => ['#71717a', '#0ea5e9', '#6366f1', '#f59e0b', '#e11d48'],
                    'legend' => ['position' => 'bottom'],
                    'dataLabels' => ['enabled' => true],
                    'noData' => ['text' => __('Sin datos en el periodo')],
                ],
            ],
            'area' => [
                'key' => 'area-'.$key,
                'series' => [[
                    'name' => __('Entregables'),
                    'data' => array_column($report['areaSeries'], 'value'),
                ]],
                'options' => $this->barOptions(array_column($report['areaSeries'], 'label'), ['#6366f1']),
            ],
            'category' => [
                'key' => 'category-'.$key,
                'series' => [[
                    'name' => __('Tickets'),
                    'data' => array_column($report['categorySeries'], 'value'),
                ]],
                'options' => $this->barOptions(array_column($report['categorySeries'], 'label'), ['#0ea5e9']),
            ],
            'weekly' => [
                'key' => 'weekly-'.$key,
                'series' => [[
                    'name' => __('Entregables creados'),
                    'data' => $report['weeklySeries']['values'],
                ]],
                'options' => [
                    'chart' => ['type' => 'area', 'height' => 280, 'toolbar' => ['show' => false]],
                    'xaxis' => ['categories' => $report['weeklySeries']['categories']],
                    'stroke' => ['curve' => 'smooth', 'width' => 2],
                    'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 0.6, 'opacityFrom' => 0.4, 'opacityTo' => 0.05]],
                    'dataLabels' => ['enabled' => false],
                    'colors' => ['#6366f1'],
                    'noData' => ['text' => __('Sin datos en el periodo')],
                ],
            ],
            'workload' => [
                'key' => 'workload-'.$key,
                'series' => [
                    [
                        'name' => __('Tareas activas'),
                        'data' => array_column($report['workloadSeries'], 'active'),
                    ],
                    [
                        'name' => __('Tareas terminadas'),
                        'data' => array_column($report['workloadSeries'], 'completed'),
                    ],
                ],
                'options' => $this->barOptions(
                    array_column($report['workloadSeries'], 'label'),
                    ['#6366f1', '#22c55e'],
                ),
            ],
        ];
    }

    public function updatedFrom(): void
    {
        $this->forgetReports();
    }

    public function updatedTo(): void
    {
        $this->forgetReports();
    }

    public function updatedAreaId(): void
    {
        $this->forgetReports();
    }

    public function updatedCategoryId(): void
    {
        $this->forgetReports();
    }

    public function updatedAssigneeId(): void
    {
        $this->forgetReports();
    }

    public function clearFilters(): void
    {
        $this->resetFilters();
        $this->forgetReports();
    }

    public function render(): View
    {
        return view('livewire.dashboard.index');
    }

    private function authenticatedUser(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    /**
     * @param  array<int, string>  $categories
     * @param  array<int, string>  $colors
     * @return array<string, mixed>
     */
    private function barOptions(array $categories, array $colors): array
    {
        return [
            'chart' => ['type' => 'bar', 'height' => 280, 'toolbar' => ['show' => false]],
            'xaxis' => ['categories' => $categories],
            'plotOptions' => ['bar' => ['borderRadius' => 4, 'columnWidth' => '55%']],
            'dataLabels' => ['enabled' => false],
            'colors' => $colors,
            'grid' => ['borderColor' => '#e4e4e7', 'strokeDashArray' => 3],
            'noData' => ['text' => __('Sin datos en el periodo')],
        ];
    }

    private function resetFilters(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
        $this->areaId = null;
        $this->categoryId = null;
        $this->assigneeId = null;
    }

    private function forgetReports(): void
    {
        unset($this->report, $this->charts);
    }
}
