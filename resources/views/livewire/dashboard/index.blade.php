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
            ['label' => __('Listas para revisión'), 'value' => $this->report['readyForReview'], 'filter' => 'review'],
            ['label' => __('Vencidas'), 'value' => $this->report['overdueDeliverables']],
            ['label' => __('Ciclo promedio (h)'), 'value' => number_format($this->report['averageCycleHours'], 1)],
        ])

        @foreach ($kpis as $kpi)
            <flux:card wire:key="dashboard-kpi-{{ $loop->index }}">
                @if (isset($kpi['filter']))
                    <button
                        type="button"
                        class="w-full text-start"
                        wire:click="setWorkItemFilter('{{ $kpi['filter'] }}')"
                        title="{{ __('Filtrar la tabla por listas para revisión') }}"
                    >
                        <flux:text>{{ $kpi['label'] }}</flux:text>
                        <flux:heading size="2xl" class="mt-1">{{ $kpi['value'] }}</flux:heading>
                    </button>
                @else
                    <flux:text>{{ $kpi['label'] }}</flux:text>
                    <flux:heading size="2xl" class="mt-1">{{ $kpi['value'] }}</flux:heading>
                @endif
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
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <flux:heading size="sm">{{ __('Entregables recientes') }}</flux:heading>
                <flux:text class="mt-1 text-xs text-zinc-400">
                    {{ __('Tickets y tareas en una sola tabla · las subtareas se muestran al expandir.') }}
                </flux:text>
            </div>

            <flux:field class="w-full sm:w-56">
                <flux:label>{{ __('Vista') }}</flux:label>
                <flux:select wire:model.live="workItemFilter">
                    <flux:select.option value="all">{{ __('Todos los recientes') }}</flux:select.option>
                    <flux:select.option value="review">{{ __('Listas para revisión') }}</flux:select.option>
                </flux:select>
            </flux:field>
        </div>

        <flux:table bleed container:class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Entregable') }}</flux:table.column>
                <flux:table.column class="w-48">{{ __('Progreso') }}</flux:table.column>
                <flux:table.column>{{ __('Área / Prioridad') }}</flux:table.column>
                <flux:table.column class="w-16">{{ __('Acciones') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->workItems as $item)
                    <flux:table.row wire:key="dashboard-work-item-{{ $item->key }}">
                        <flux:table.cell class="align-top">
                            <div class="flex items-start gap-2">
                                <flux:badge size="sm" :color="$item->ticketId === null ? 'indigo' : 'sky'">
                                    {{ $item->sourceLabel }}
                                </flux:badge>

                                <div class="min-w-0 flex-1">
                                    @if ($item->hasSubtasks)
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-1 text-start"
                                            wire:click="toggleWorkItem('{{ $item->key }}')"
                                            aria-expanded="{{ $expandedWorkItemId === $item->key ? 'true' : 'false' }}"
                                        >
                                            <span
                                                class="block max-w-sm truncate text-sm font-medium text-zinc-800 dark:text-zinc-100"
                                                title="{{ $item->displayTitle }}"
                                            >
                                                {{ $item->displayTitle }}
                                            </span>
                                            <flux:icon.chevron-down
                                                class="size-4 shrink-0 text-zinc-400 transition"
                                                :class="$expandedWorkItemId === $item->key ? 'rotate-180' : ''"
                                            />
                                        </button>
                                    @else
                                        <span
                                            class="block max-w-sm truncate text-sm font-medium text-zinc-800 dark:text-zinc-100"
                                            title="{{ $item->displayTitle }}"
                                        >
                                            {{ $item->displayTitle }}
                                        </span>
                                    @endif

                                    <flux:text class="mt-0.5 text-xs">
                                        {{ $item->assigneeName ?? __('Sin responsable') }}
                                    </flux:text>

                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <flux:badge size="sm" :color="$item->status->badgeColor()">
                                            {{ $item->status->label() }}
                                        </flux:badge>
                                        <flux:text class="text-xs text-zinc-400">
                                            {{ __('por :name · :date', [
                                                'name' => $item->statusChangedByName ?? __('Sistema'),
                                                'date' => $item->statusChangedAt ?? '—',
                                            ]) }}
                                        </flux:text>
                                        @if ($item->isReadyForReview)
                                            <flux:badge size="sm" color="amber">
                                                {{ __('Lista para revisión') }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="align-top">
                            @if ($item->showProgressBar)
                                <div class="flex items-center gap-2">
                                    <flux:progress
                                        :value="$item->progressPercent"
                                        :color="$item->progressColor"
                                        class="h-2 flex-1"
                                    />
                                    <flux:text class="whitespace-nowrap text-xs">
                                        {{ $item->progressLabel }}
                                    </flux:text>
                                </div>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Sin desglose') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="align-top">
                            <flux:text class="text-xs">{{ $item->areaName ?? '—' }}</flux:text>
                            @if ($item->priority !== null)
                                <div class="mt-1">
                                    <flux:badge size="sm" :color="$item->priority->badgeColor()">
                                        {{ $item->priority->label() }}
                                    </flux:badge>
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="align-top">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button
                                    icon="ellipsis-horizontal"
                                    size="xs"
                                    variant="ghost"
                                    :tooltip="__('Acciones')"
                                />

                                <flux:menu>
                                    <flux:menu.item
                                        icon="eye"
                                        wire:click="openDetails('{{ $item->key }}')"
                                    >
                                        {{ __('Detalles') }}
                                    </flux:menu.item>

                                    @if ($item->ticketId !== null)
                                        <flux:menu.item
                                            icon="ticket"
                                            :href="route('tickets.index', ['search' => $item->code])"
                                        >
                                            {{ __('Ver ticket') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>

                    @if ($item->hasSubtasks && $expandedWorkItemId === $item->key)
                        <flux:table.row wire:key="dashboard-work-item-detail-{{ $item->key }}">
                            <flux:table.cell colspan="4">
                                <div class="rounded-lg border border-zinc-200/80 bg-zinc-50 p-3 dark:border-zinc-700/60 dark:bg-zinc-900/40">
                                    <flux:heading size="sm">
                                        {{ __('Subtareas de :title', ['title' => $item->subject]) }} ·
                                        {{ $item->progressLabel }}
                                    </flux:heading>

                                    <div class="mt-2 flex flex-col gap-2">
                                        @forelse ($this->expandedSubtasks as $subtask)
                                            <div
                                                class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-white px-3 py-2 text-sm dark:bg-zinc-900"
                                                wire:key="dashboard-work-item-subtask-{{ $subtask->id }}"
                                            >
                                                <div class="min-w-0 flex-1">
                                                    <span class="block truncate">{{ $subtask->title }}</span>
                                                    <flux:text class="text-xs text-zinc-400">
                                                        {{ __('Subtarea de: :title', ['title' => $item->subject]) }}
                                                    </flux:text>
                                                </div>
                                                <flux:text class="text-xs">
                                                    {{ $subtask->assignee?->name ?? __('Sin responsable') }}
                                                </flux:text>
                                                <flux:badge size="sm" :color="$subtask->status->badgeColor()">
                                                    {{ $subtask->status->label() }}
                                                </flux:badge>
                                                <flux:text class="text-xs text-zinc-400">
                                                    {{ $subtask->completed_at?->format('d/m/Y H:i') ?? '—' }}
                                                </flux:text>
                                            </div>
                                        @empty
                                            <flux:text class="text-sm">{{ __('Sin subtareas.') }}</flux:text>
                                        @endforelse
                                    </div>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endif
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <flux:text class="text-sm">
                                {{ $workItemFilter === 'review'
                                    ? __('No hay entregables pendientes de revisión.')
                                    : __('No hay entregables en el periodo seleccionado.') }}
                            </flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal
        wire:model.self="showDetailsModal"
        name="work-item-details"
        class="md:w-2xl"
        @close="$wire.closeDetails()"
    >
        @if ($this->detailsWorkItem)
            @php($detail = $this->detailsWorkItem)
            <div class="flex flex-col gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$detail->ticketId === null ? 'indigo' : 'sky'">
                            {{ $detail->sourceLabel }}
                        </flux:badge>
                        <flux:badge size="sm" :color="$detail->status->badgeColor()">
                            {{ $detail->status->label() }}
                        </flux:badge>
                    </div>
                    <flux:heading size="lg" class="mt-2">{{ $detail->displayTitle }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $detail->description ?: __('Sin descripción.') }}
                    </flux:text>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <flux:text class="text-xs">{{ __('Código') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->code ?? __('Creada en el tablero') }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Título') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->subject }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Área') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->areaName ?? '—' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Categoría') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->categoryName ?? '—' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Prioridad') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->priority?->label() ?? '—' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Responsable') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->assigneeName ?? __('Sin responsable') }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Solicitante') }}</flux:text>
                        <p class="text-sm font-medium">
                            {{ $detail->requesterName ?? '—' }}
                            @if ($detail->requesterEmail !== null)
                                <span class="text-xs text-zinc-400">({{ $detail->requesterEmail }})</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Creado por') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->createdByName ?? '—' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Último estado') }}</flux:text>
                        <p class="text-sm font-medium">
                            {{ $detail->statusChangedByName ?? __('Sistema') }} · {{ $detail->statusChangedAt ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Progreso') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->progressLabel }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Fecha de creación') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->createdAt }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Fecha de asignación') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->assignedAt ?? '—' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Fecha de cierre') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->closedAt ?? '—' }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    @if ($detail->ticketId !== null)
                        <flux:button variant="ghost" :href="route('tickets.index', ['search' => $detail->code])" icon="ticket">
                            {{ __('Ver ticket') }}
                        </flux:button>
                    @endif
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cerrar') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
