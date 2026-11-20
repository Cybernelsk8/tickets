<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Indicadores de entregables, subtareas y tickets') }} · {{ $this->report['scopeLabel'] }}
            </flux:text>
        </div>

        <flux:badge color="sky">{{ __('Últimos :days días', ['days' => 30]) }}</flux:badge>
    </div>

    <flux:card>
        <div class="flex flex-wrap items-end gap-3">
            <flux:field class="w-full sm:w-40">
                <flux:label>{{ __('Desde') }}</flux:label>
                <flux:input type="date" wire:model.live="from" />
            </flux:field>

            <flux:field class="w-full sm:w-40">
                <flux:label>{{ __('Hasta') }}</flux:label>
                <flux:input type="date" wire:model.live="to" />
            </flux:field>

            <flux:field class="w-full sm:w-48">
                <flux:label>{{ __('Área') }}</flux:label>
                <flux:select wire:model.live="areaId">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach ($this->areas as $area)
                        <flux:select.option :value="(string) $area->id" wire:key="dashboard-area-{{ $area->id }}">
                            {{ $area->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field class="w-full sm:w-48">
                <flux:label>{{ __('Categoría') }}</flux:label>
                <flux:select wire:model.live="categoryId">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option :value="(string) $category->id" wire:key="dashboard-category-{{ $category->id }}">
                            {{ $category->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field class="w-full sm:w-48">
                <flux:label>{{ __('Responsable') }}</flux:label>
                <flux:select wire:model.live="assigneeId">
                    <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                    @foreach ($this->assignees as $assignee)
                        <flux:select.option :value="(string) $assignee->id" wire:key="dashboard-assignee-{{ $assignee->id }}">
                            {{ $assignee->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:button size="sm" variant="ghost" wire:click="clearFilters">
                {{ __('Limpiar') }}
            </flux:button>
        </div>

        <flux:text class="mt-3 text-xs text-zinc-400" wire:loading.delay>
            {{ __('Actualizando indicadores...') }}
        </flux:text>
    </flux:card>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        @php($kpis = [
            ['label' => __('Total de tickets'), 'value' => $this->report['totalTickets']],
            ['label' => __('Tickets abiertos'), 'value' => $this->report['openTickets']],
            ['label' => __('Entregables'), 'value' => $this->report['totalDeliverables']],
            ['label' => __('Entregables terminados'), 'value' => $this->report['completedDeliverables']],
            ['label' => __('% entregables terminados'), 'value' => number_format($this->report['deliverableCompletionRate'], 1).' %'],
            ['label' => __('Subtareas terminadas'), 'value' => $this->report['completedSubtasks']],
            ['label' => __('Avance del desglose'), 'value' => number_format($this->report['subtaskProgressRate'], 1).' %'],
            ['label' => __('Listas para revisión'), 'value' => $this->report['readyForReview']],
            ['label' => __('Vencidas'), 'value' => $this->report['overdueDeliverables']],
            ['label' => __('Ciclo promedio (h)'), 'value' => number_format($this->report['averageCycleHours'], 1)],
        ])

        @foreach ($kpis as $kpi)
            <flux:card wire:key="dashboard-kpi-{{ $loop->index }}">
                <flux:text>{{ $kpi['label'] }}</flux:text>
                <flux:heading size="2xl" class="mt-1">{{ $kpi['value'] }}</flux:heading>
            </flux:card>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @php($topCharts = [
            'status' => __('Entregables por estado'),
            'priority' => __('Tickets por prioridad'),
            'area' => __('Entregables por área'),
            'category' => __('Tickets por categoría'),
        ])

        @foreach ($topCharts as $chartKey => $chartTitle)
            <flux:card>
                <flux:heading size="sm">{{ $chartTitle }}</flux:heading>
                <div class="mt-3">
                    <x-apex-chart
                        :chart-key="$this->charts[$chartKey]['key']"
                        :series="$this->charts[$chartKey]['series']"
                        :options="$this->charts[$chartKey]['options']"
                    />
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:card>
        <flux:heading size="sm">{{ __('Tendencia semanal de entregables creados') }}</flux:heading>
        <div class="mt-3">
            <x-apex-chart
                :chart-key="$this->charts['weekly']['key']"
                :series="$this->charts['weekly']['series']"
                :options="$this->charts['weekly']['options']"
            />
        </div>
    </flux:card>

    <div class="grid gap-4 lg:grid-cols-3">
        <flux:card>
            <flux:heading size="sm">{{ __('Carga por colaborador') }}</flux:heading>
            <div class="mt-3">
                <x-apex-chart
                    :chart-key="$this->charts['workload']['key']"
                    :series="$this->charts['workload']['series']"
                    :options="$this->charts['workload']['options']"
                />
            </div>
        </flux:card>

        <flux:card class="lg:col-span-2">
            <flux:heading size="sm">{{ __('Tiempo promedio por etapa') }}</flux:heading>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach ($this->report['stageAverages'] as $stage)
                    <div
                        class="rounded-lg border border-zinc-200/80 bg-zinc-50 p-3 dark:border-zinc-700/60 dark:bg-zinc-900/40"
                        wire:key="dashboard-stage-{{ $loop->index }}"
                    >
                        <flux:text class="text-xs">{{ $stage['label'] }}</flux:text>
                        <flux:heading size="lg" class="mt-1">{{ number_format($stage['hours'], 1) }} h</flux:heading>
                    </div>
                @endforeach
            </div>

            <flux:text class="mt-4 text-xs text-zinc-400">
                {{ __('Calculado a partir de las marcas de inicio, pruebas y terminado de cada entregable.') }}
            </flux:text>
        </flux:card>
    </div>

    <flux:card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="sm">{{ __('Tareas recientes') }}</flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ __('Máximo 5 resultados · raíces y subtareas') }}</flux:text>
        </div>

        <flux:table bleed container:class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Tipo') }}</flux:table.column>
                <flux:table.column>{{ __('Título') }}</flux:table.column>
                <flux:table.column>{{ __('Responsable') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Terminado') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->report['recentTasks'] as $task)
                    <flux:table.row wire:key="dashboard-recent-task-{{ $task->id }}">
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$task->parent_id === null ? 'indigo' : 'amber'">
                                {{ $task->parent_id === null ? __('Tarea') : __('Subtarea') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">
                            <span class="block max-w-xs truncate" title="{{ $task->title }}">{{ $task->title }}</span>
                            @if ($task->parent !== null)
                                <flux:text class="text-xs">{{ __('Subtarea de: :title', ['title' => $task->parent->title]) }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $task->assignee?->name ?? __('Sin responsable') }}</flux:table.cell>
                        <flux:table.cell class="py-0">
                            <flux:badge size="sm" :color="$task->status->badgeColor()">
                                {{ $task->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $task->completed_at?->format('d/m/Y H:i') ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <flux:text class="text-sm">{{ __('No hay tareas en el periodo seleccionado.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="sm">{{ __('Listas para revisión') }}</flux:heading>
            <flux:text class="text-xs text-zinc-400">
                {{ __('Subtareas terminadas; el jefe da la aprobación final.') }}
            </flux:text>
        </div>

        <flux:table bleed container:class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Entregable') }}</flux:table.column>
                <flux:table.column>{{ __('Responsable') }}</flux:table.column>
                <flux:table.column>{{ __('Subtareas') }}</flux:table.column>
                <flux:table.column>{{ __('Área') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->report['readyForReviewTasks'] as $task)
                    <flux:table.row wire:key="dashboard-review-task-{{ $task->id }}">
                        <flux:table.cell variant="strong">
                            <span class="block max-w-sm truncate" title="{{ $task->title }}">{{ $task->title }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $task->assignee?->name ?? __('Sin responsable') }}</flux:table.cell>
                        <flux:table.cell>{{ $task->subtasks_done_count }}/{{ $task->subtasks_total_count }}</flux:table.cell>
                        <flux:table.cell>{{ $task->area?->name ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <flux:text class="text-sm">{{ __('No hay entregables pendientes de revisión.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="sm">{{ __('Tickets recientes') }}</flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ __('Máximo 5 resultados') }}</flux:text>
        </div>

        <flux:table bleed container:class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Código') }}</flux:table.column>
                <flux:table.column>{{ __('Asunto') }}</flux:table.column>
                <flux:table.column>{{ __('Área') }}</flux:table.column>
                <flux:table.column>{{ __('Categoría') }}</flux:table.column>
                <flux:table.column>{{ __('Prioridad') }}</flux:table.column>
                <flux:table.column>{{ __('Responsable') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->report['recentTickets'] as $ticket)
                    <flux:table.row wire:key="dashboard-recent-ticket-{{ $ticket->id }}">
                        <flux:table.cell variant="strong">{{ $ticket->code }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="block max-w-xs truncate" title="{{ $ticket->subject }}">{{ $ticket->subject }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $ticket->area?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $ticket->category?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="py-0">
                            <flux:badge size="sm" :color="$ticket->priority->badgeColor()">
                                {{ $ticket->priority->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $ticket->assignee?->name ?? __('Sin responsable') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text class="text-sm">{{ __('No hay tickets en el periodo seleccionado.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
