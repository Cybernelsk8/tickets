<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Tareas') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kanban por área: tareas de tickets y tareas creadas en el tablero.') }}</flux:text>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <flux:field class="w-full sm:w-56">
                <flux:label>{{ __('Alcance') }}</flux:label>
                <flux:select wire:model.live="scope">
                    @foreach ($this->scopeOptions() as $option)
                        <flux:select.option :value="$option['id']" wire:key="board-scope-{{ $option['id'] }}">
                            {{ $option['label'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            @if ($scope !== 'all')
                <flux:field class="w-full sm:w-56">
                    <flux:label>{{ __('Área') }}</flux:label>
                    <flux:select wire:model.live="areaId">
                        @foreach ($this->areas as $area)
                            <flux:select.option :value="(string) $area->id" wire:key="board-area-{{ $area->id }}">
                                {{ $area->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            @endif
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-zinc-200/80 bg-white p-3 dark:border-zinc-700/60 dark:bg-zinc-900/40">
            <flux:field class="w-full sm:w-72">
                <flux:label>{{ __('Buscar') }}</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('Título, código de ticket o responsable')"
                />
            </flux:field>

            <flux:field class="w-full sm:w-52">
                <flux:label>{{ __('Responsable') }}</flux:label>
                <flux:select wire:model.live="filterAssigneeId">
                    <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                    @foreach ($this->filterAssignees as $assignee)
                        <flux:select.option :value="(string) $assignee->id" wire:key="board-filter-assignee-{{ $assignee->id }}">
                            {{ $assignee->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field class="w-full sm:w-44">
                <flux:label>{{ __('Prioridad') }}</flux:label>
                <flux:select wire:model.live="filterPriority">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach (\App\TicketPriority::cases() as $case)
                        <flux:select.option :value="$case->value" wire:key="board-filter-priority-{{ $case->value }}">
                            {{ $case->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field class="w-full sm:w-48">
                <flux:label>{{ __('Fecha límite') }}</flux:label>
                <flux:select wire:model.live="filterDue">
                    @foreach ($this->dueFilterOptions() as $option)
                        <flux:select.option :value="$option['id']" wire:key="board-filter-due-{{ $option['id'] }}">
                            {{ $option['label'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="flex items-end gap-2">
                <flux:button
                    size="sm"
                    :variant="$viewMode === 'board' ? 'primary' : 'ghost'"
                    wire:click="setViewMode('board')"
                >
                    {{ __('Kanban') }}
                </flux:button>
                <flux:button
                    size="sm"
                    :variant="$viewMode === 'list' ? 'primary' : 'ghost'"
                    wire:click="setViewMode('list')"
                >
                    {{ __('Lista') }}
                </flux:button>
                <flux:button size="sm" variant="ghost" wire:click="clearFilters">
                    {{ __('Limpiar') }}
                </flux:button>
            </div>
        </div>
    </div>

    @error('board')
        <flux:callout variant="danger" :heading="$message" />
    @enderror

    @if ($this->areas->isEmpty())
        <flux:card>
            <flux:text>{{ __('No tienes áreas disponibles para mostrar un tablero.') }}</flux:text>
        </flux:card>
    @elseif ($viewMode === 'board')
        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($this->columns as $column)
                <section class="flex min-h-[24rem] flex-col rounded-xl border border-zinc-200/80 bg-zinc-50/60 p-3 dark:border-zinc-700/60 dark:bg-zinc-900/40">
                    <div class="flex items-center justify-between gap-2 px-1">
                        <div class="flex items-baseline gap-2">
                            <flux:heading size="sm">{{ $column['label'] }}</flux:heading>
                            <span class="text-xs text-zinc-400">{{ $column['total'] }}</span>
                        </div>

                        @can('create', \App\Models\Task::class)
                            <flux:tooltip :content="__('Nueva tarea')" position="bottom">
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="plus"
                                    wire:click="openCreate('{{ $column['id'] }}')"
                                    wire:sort:ignore
                                />
                            </flux:tooltip>
                        @endcan
                    </div>

                    <div
                        class="mt-3 flex min-h-[18rem] flex-1 flex-col gap-2"
                        wire:sort="handleSort"
                        wire:sort:group="board-tasks"
                        wire:sort:group-id="{{ $column['id'] }}"
                    >
                        @forelse ($column['tasks'] as $task)
                            @php
                                $areaColor = is_string($task->area?->color)
                                    && preg_match('/^#[0-9a-fA-F]{6}$/', $task->area->color)
                                        ? $task->area->color
                                        : '#71717a';
                                $movement = $this->latestColumnMovement($task);
                                $isExpanded = $expandedTaskId === $task->id;
                                $quickTransitions = $this->quickTransitions($task);
                            @endphp
                            <div
                                wire:key="board-task-{{ $task->id }}"
                                wire:sort:item="{{ $task->id }}"
                                wire:click="toggleCard({{ $task->id }})"
                                class="group relative cursor-pointer rounded-lg border border-zinc-200/80 bg-white p-3 shadow-xs transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700/70 dark:bg-zinc-900"
                            >
                                <div wire:sort:ignore @click.stop class="absolute start-2 top-2 z-10">
                                    <flux:dropdown position="bottom" align="start">
                                        <flux:button
                                            icon="ellipsis-vertical"
                                            size="xs"
                                            variant="ghost"
                                            :tooltip="__('Acciones')"
                                        />

                                        <flux:menu>
                                            <flux:menu.item
                                                icon="eye"
                                                wire:click.stop="openDetails({{ $task->id }})"
                                            >
                                                {{ __('Ver detalle') }}
                                            </flux:menu.item>

                                            @can('createSubtask', $task)
                                                <flux:menu.item
                                                    icon="plus"
                                                    wire:click.stop="openSubtask({{ $task->id }})"
                                                >
                                                    {{ __('Crear subtarea') }}
                                                </flux:menu.item>
                                            @endcan

                                            @can('update', $task)
                                                <flux:menu.item
                                                    icon="pencil-square"
                                                    wire:click.stop="openEdit({{ $task->id }})"
                                                >
                                                    {{ __('Editar tarea') }}
                                                </flux:menu.item>
                                            @endcan
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>

                                <div class="flex items-start justify-between gap-2 ps-6">
                                    <p
                                        class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-800 dark:text-zinc-100"
                                        title="{{ $task->title }}"
                                    >
                                        {{ $task->title }}
                                    </p>

                                    @if ($task->assignee)
                                        <flux:avatar
                                            circle
                                            size="xs"
                                            color="auto"
                                            color:seed="{{ $task->assignee_id }}"
                                            name="{{ $task->assignee->name }}"
                                            :tooltip="$task->due_at
                                                ? __(':name · vence :date', [
                                                    'name' => $task->assignee->name,
                                                    'date' => $task->due_at->format('d/m/Y'),
                                                ])
                                                : __(':name · sin fecha límite', ['name' => $task->assignee->name])"
                                            tooltip:position="top"
                                        />
                                    @else
                                        <flux:avatar
                                            circle
                                            size="xs"
                                            icon="user"
                                            :tooltip="__('Sin responsable')"
                                            tooltip:position="top"
                                        />
                                    @endif
                                </div>

                                @if ($isExpanded)
                                    <div class="mt-3 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <flux:badge size="sm" :color="$task->status->badgeColor()">
                                                {{ $task->status->label() }}
                                            </flux:badge>

                                            <flux:badge size="sm" :color="$task->priority->badgeColor()">
                                                {{ $task->priority->label() }}
                                            </flux:badge>

                                            @if ($task->ticket)
                                                <flux:badge size="sm" color="sky">{{ $task->ticket->code }}</flux:badge>
                                            @endif

                                            @if ($task->parent)
                                                <flux:badge size="sm" color="amber">{{ __('Subtarea') }}</flux:badge>
                                            @endif

                                            @if ($task->status === \App\TaskStatus::Blocked)
                                                <flux:badge size="sm" color="rose">{{ __('Bloqueado') }}</flux:badge>
                                            @endif
                                        </div>

                                        @if ($task->parent)
                                            <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                                                {{ __('Subtarea de') }}:
                                                <span class="font-medium">{{ $task->parent->title }}</span>
                                            </p>
                                        @endif

                                        <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-zinc-500">
                                            <span class="size-2 rounded-full" style="background-color: {{ $areaColor }}"></span>
                                            <span>{{ $task->area?->name ?? __('Sin área') }}</span>
                                            <span>·</span>
                                            <span>{{ $task->status->kanbanColumnLabel() }}</span>

                                            @if ($task->due_at)
                                                <span>·</span>
                                                <span>{{ __('Vence') }} {{ $task->due_at->format('d/m/Y') }}</span>
                                            @endif
                                        </div>

                                        @if ($movement)
                                            <p class="mt-2 text-xs text-sky-600 dark:text-sky-400">
                                                {{ __('Movida') }}:
                                                {{ $movement->fromColumnLabel() ?? __('Inicio') }}
                                                → {{ $movement->toColumnLabel() }}
                                                <span class="text-zinc-400">
                                                    {{ $movement->occurred_at->format('d/m/Y H:i') }}
                                                </span>
                                            </p>
                                        @endif

                                        @if ($task->dependencies->contains(fn ($dependency) => $dependency->dependsOnTask?->status !== \App\TaskStatus::Done))
                                            <p class="mt-2 text-xs text-amber-600">
                                                {{ __('Dependencia pendiente') }}
                                            </p>
                                        @endif

                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                            <flux:button
                                                size="xs"
                                                variant="ghost"
                                                icon="eye"
                                                wire:click.stop="openDetails({{ $task->id }})"
                                            >
                                                {{ __('Detalle') }}
                                            </flux:button>

                                            @foreach ($quickTransitions as $transition)
                                                <flux:button
                                                    size="xs"
                                                    variant="ghost"
                                                    wire:click.stop="moveTask({{ $task->id }}, '{{ $transition['value'] }}')"
                                                >
                                                    {{ __('Pasar a :status', ['status' => $transition['label']]) }}
                                                </flux:button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-center text-xs text-zinc-400 dark:border-zinc-700">
                                {{ __('Suelta tareas aquí') }}
                            </div>
                        @endforelse
                    </div>

                    @if ($column['hasMore'])
                        <flux:button
                            size="xs"
                            variant="ghost"
                            class="mt-2 w-full"
                            wire:click.preserve-scroll="loadMore('{{ $column['id'] }}')"
                        >
                            {{ __('Cargar más') }}
                        </flux:button>
                    @endif

                    @if ($column['id'] === 'done' && $this->historyGroups !== [])
                        <div class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                            <flux:heading size="sm">{{ __('Historial por fecha') }}</flux:heading>

                            @foreach ($this->historyGroups as $group)
                                <div class="mt-2" wire:key="history-group-{{ $group['month'] }}">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-start text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                        wire:click="toggleHistoryGroup('{{ $group['month'] }}')"
                                    >
                                        <span>{{ $group['label'] }}</span>
                                        <span class="text-zinc-400">{{ $group['total'] }}</span>
                                    </button>

                                    @if ($openHistoryGroup === $group['month'])
                                        @php($historyTasks = $this->historyTasks($group['month']))
                                        <div class="mt-1 space-y-1">
                                            @forelse ($historyTasks as $historyTask)
                                                <div
                                                    class="flex items-center justify-between gap-2 rounded-md bg-white px-2 py-1.5 text-xs dark:bg-zinc-900"
                                                    wire:key="history-task-{{ $historyTask->id }}"
                                                >
                                                    <span class="min-w-0 flex-1 truncate" title="{{ $historyTask->title }}">
                                                        {{ $historyTask->title }}
                                                    </span>
                                                    <flux:button
                                                        size="xs"
                                                        variant="ghost"
                                                        icon="eye"
                                                        wire:click="openDetails({{ $historyTask->id }})"
                                                    />
                                                </div>
                                            @empty
                                                <flux:text class="px-2 text-xs">{{ __('Sin tareas en este periodo.') }}</flux:text>
                                            @endforelse

                                            {{ $historyTasks->links() }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    @else
        <flux:card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="sm">{{ __('Lista de tareas') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ trans_choice(':count resultado|:count resultados', $this->listTasks->total(), ['count' => $this->listTasks->total()]) }}
                    </flux:text>
                </div>
            </div>

            <flux:table :paginate="$this->listTasks" bleed container:class="mt-4">
                <flux:table.columns>
                    <flux:table.column>{{ __('Tarea') }}</flux:table.column>
                    <flux:table.column>{{ __('Área') }}</flux:table.column>
                    <flux:table.column>{{ __('Responsable') }}</flux:table.column>
                    <flux:table.column>{{ __('Estado') }}</flux:table.column>
                    <flux:table.column>{{ __('Prioridad') }}</flux:table.column>
                    <flux:table.column>{{ __('Vencimiento') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->listTasks as $task)
                        <flux:table.row :key="$task->id" wire:key="list-task-{{ $task->id }}">
                            <flux:table.cell variant="strong">
                                <button
                                    type="button"
                                    class="text-start font-medium text-blue-600 hover:underline dark:text-blue-400"
                                    wire:click="openDetails({{ $task->id }})"
                                >
                                    {{ $task->title }}
                                </button>
                            </flux:table.cell>
                            <flux:table.cell>{{ $task->area?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $task->assignee?->name ?? __('Sin responsable') }}</flux:table.cell>
                            <flux:table.cell class="py-0">
                                <flux:badge size="sm" :color="$task->status->badgeColor()">
                                    {{ $task->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="py-0">
                                <flux:badge size="sm" :color="$task->priority->badgeColor()">
                                    {{ $task->priority->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $task->due_at?->format('d/m/Y') ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">
                                <flux:text class="text-sm">{{ __('No hay tareas que coincidan con los filtros.') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    <flux:modal wire:model.self="showCreateModal" name="create-task" class="md:w-2xl">
        @php($canCreateTask = $this->canCreateTask())
        @php($canChooseAssignee = $this->canChooseAssignee())

        <form wire:submit="createTask" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ $canCreateTask ? __('Nueva tarea') : __('Nueva subtarea') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $canCreateTask
                        ? ($canChooseAssignee
                            ? __('Puedes crear una tarea independiente o un desglose de otra tarea.')
                            : __('La tarea se asignará a ti y comenzará en Por hacer.'))
                        : __('Divide esta tarea en pasos más pequeños. La subtarea se asignará a ti.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Título') }}</flux:label>
                <flux:input wire:model="title" :placeholder="__('¿Qué debe hacerse?')" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Descripción') }}</flux:label>
                <flux:textarea wire:model="description" rows="4" :placeholder="__('Detalles, criterios de aceptación o notas.')" />
                <flux:error name="description" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                @if ($canCreateTask)
                    <flux:field>
                        <flux:label>{{ __('Tarea padre (opcional)') }}</flux:label>
                        <flux:select wire:model="parentTaskId">
                            <flux:select.option value="">{{ __('Ninguna: tarea raíz') }}</flux:select.option>
                            @foreach ($this->parentTasks as $parentTask)
                                <flux:select.option :value="(string) $parentTask->id" wire:key="new-task-parent-{{ $parentTask->id }}">
                                    {{ $parentTask->title }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="parentTaskId" />
                    </flux:field>

                    @if ($canChooseAssignee)
                        <flux:field>
                            <flux:label>{{ __('Responsable') }}</flux:label>
                            <flux:select wire:model="assigneeId">
                                <flux:select.option value="">{{ __('Sin responsable') }}</flux:select.option>
                                @foreach ($this->assignees as $assignee)
                                    <flux:select.option :value="(string) $assignee->id" wire:key="new-task-assignee-{{ $assignee->id }}">
                                        {{ $assignee->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="assigneeId" />
                        </flux:field>
                    @else
                        <flux:field>
                            <flux:label>{{ __('Responsable') }}</flux:label>
                            <flux:input :value="auth()->user()?->name ?? ''" disabled />
                        </flux:field>
                    @endif

                    @if ($canChooseAssignee)
                        <flux:field>
                            <flux:label>{{ __('Columna inicial') }}</flux:label>
                            <flux:select wire:model="targetStatus">
                                @foreach ($this->columns as $column)
                                    <flux:select.option :value="$column['id']" wire:key="new-task-status-{{ $column['id'] }}">
                                        {{ $column['label'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="targetStatus" />
                        </flux:field>
                    @else
                        <flux:field>
                            <flux:label>{{ __('Columna inicial') }}</flux:label>
                            <flux:input :value="\App\TaskStatus::Todo->kanbanColumnLabel()" disabled />
                        </flux:field>
                    @endif
                @else
                    <flux:field>
                        <flux:label>{{ __('Tarea padre') }}</flux:label>
                        <flux:input
                            :value="$this->availableTasks->firstWhere('id', $parentTaskId)?->title ?? ''"
                            disabled
                        />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Responsable') }}</flux:label>
                        <flux:input :value="auth()->user()?->name ?? ''" disabled />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Columna inicial') }}</flux:label>
                        <flux:input :value="\App\TaskStatus::Todo->kanbanColumnLabel()" disabled />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('Prioridad') }}</flux:label>
                    <flux:select wire:model="priority">
                        @foreach (\App\TicketPriority::cases() as $case)
                            <flux:select.option :value="$case->value" wire:key="new-task-priority-{{ $case->value }}">
                                {{ $case->label() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="priority" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Depende de (opcional)') }}</flux:label>
                    <flux:select wire:model="dependsOnTaskId">
                        <flux:select.option value="">{{ __('Sin dependencia') }}</flux:select.option>
                        @foreach ($this->availableTasks as $availableTask)
                            <flux:select.option :value="(string) $availableTask->id" wire:key="new-task-dependency-{{ $availableTask->id }}">
                                {{ $availableTask->title }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="dependsOnTaskId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fecha límite') }}</flux:label>
                    <flux:input type="date" wire:model="dueAt" />
                    <flux:error name="dueAt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Tiempo estimado (minutos)') }}</flux:label>
                    <flux:input type="number" min="0" wire:model="estimatedMinutes" />
                    <flux:error name="estimatedMinutes" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Tiempo real (minutos)') }}</flux:label>
                    <flux:input type="number" min="0" wire:model="actualMinutes" />
                    <flux:error name="actualMinutes" />
                </flux:field>
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ $canCreateTask ? __('Crear tarea') : __('Crear subtarea') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showEditModal" name="edit-task" class="md:w-2xl">
        @php($canReassign = $this->canReassignEditedTask())

        <form wire:submit="updateTask" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ __('Editar tarea') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $canReassign
                        ? __('Puedes corregir el responsable y los datos de esta tarea.')
                        : __('Puedes corregir los datos de tu tarea; el responsable no se puede cambiar.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Título') }}</flux:label>
                <flux:input wire:model="editTitle" />
                <flux:error name="editTitle" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Descripción') }}</flux:label>
                <flux:textarea wire:model="editDescription" rows="3" />
                <flux:error name="editDescription" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Prioridad') }}</flux:label>
                    <flux:select wire:model="editPriority">
                        @foreach (\App\TicketPriority::cases() as $case)
                            <flux:select.option :value="$case->value" wire:key="edit-task-priority-{{ $case->value }}">
                                {{ $case->label() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="editPriority" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Responsable') }}</flux:label>
                    @if ($canReassign)
                        <flux:select wire:model="editAssigneeId">
                            <flux:select.option value="">{{ __('Sin responsable') }}</flux:select.option>
                            @foreach ($this->editAssignees as $assignee)
                                <flux:select.option :value="(string) $assignee->id" wire:key="edit-task-assignee-{{ $assignee->id }}">
                                    {{ $assignee->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input
                            :value="$this->editAssignees->firstWhere('id', $editAssigneeId)?->name ?? __('Sin responsable')"
                            disabled
                        />
                    @endif
                    <flux:error name="editAssigneeId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Tarea padre (opcional)') }}</flux:label>
                    <flux:select wire:model="editParentTaskId">
                        <flux:select.option value="">{{ __('Ninguna: tarea raíz') }}</flux:select.option>
                        @foreach ($this->editAvailableTasks as $availableTask)
                            @if ($availableTask->id !== $editTaskId)
                                <flux:select.option :value="(string) $availableTask->id" wire:key="edit-task-parent-{{ $availableTask->id }}">
                                    {{ $availableTask->title }}
                                </flux:select.option>
                            @endif
                        @endforeach
                    </flux:select>
                    <flux:error name="editParentTaskId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Depende de (opcional)') }}</flux:label>
                    <flux:select wire:model="editDependsOnTaskId">
                        <flux:select.option value="">{{ __('Sin dependencia') }}</flux:select.option>
                        @foreach ($this->editAvailableTasks as $availableTask)
                            @if ($availableTask->id !== $editTaskId)
                                <flux:select.option :value="(string) $availableTask->id" wire:key="edit-task-dependency-{{ $availableTask->id }}">
                                    {{ $availableTask->title }}
                                </flux:select.option>
                            @endif
                        @endforeach
                    </flux:select>
                    <flux:error name="editDependsOnTaskId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fecha límite') }}</flux:label>
                    <flux:input type="date" wire:model="editDueAt" />
                    <flux:error name="editDueAt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Tiempo estimado (minutos)') }}</flux:label>
                    <flux:input type="number" min="0" wire:model="editEstimatedMinutes" />
                    <flux:error name="editEstimatedMinutes" />
                </flux:field>
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Guardar cambios') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDetailsModal" name="task-details" class="md:w-2xl" @close="$wire.detailsTaskId = null">
        @if ($this->detailsTask())
            @php($detail = $this->detailsTask())
            <div class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">{{ $detail->title }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $detail->description ?: __('Sin descripción.') }}
                    </flux:text>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <flux:text class="text-xs">{{ __('Estado') }}</flux:text>
                        <p class="text-sm font-medium">
                            {{ $detail->status->label() }} · {{ $detail->status->kanbanColumnLabel() }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Origen') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->ticket?->code ?? __('Creada en el tablero') }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Área') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->area?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Responsable') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->assignee?->name ?? __('Sin responsable') }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Tarea padre') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->parent?->title ?? '—' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Fecha límite') }}</flux:text>
                        <p class="text-sm font-medium">{{ $detail->due_at?->format('d/m/Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Tiempos') }}</flux:text>
                        <p class="text-sm font-medium">
                            {{ __('Estimado') }}: {{ $detail->estimated_minutes ?? '—' }} ·
                            {{ __('Real') }}: {{ $detail->actual_minutes ?? '—' }}
                        </p>
                    </div>
                </div>

                <flux:separator />

                <div>
                    <flux:heading size="sm">{{ __('Subtareas') }}</flux:heading>
                    @forelse ($detail->children as $child)
                        <p class="mt-1 text-sm">
                            • {{ $child->title }} ({{ $child->status->label() }} ·
                            {{ $child->status->kanbanColumnLabel() }})
                        </p>
                    @empty
                        <flux:text class="mt-1">{{ __('Sin subtareas.') }}</flux:text>
                    @endforelse
                </div>

                <div>
                    <flux:heading size="sm">{{ __('Dependencias') }}</flux:heading>
                    @forelse ($detail->dependencies as $dependency)
                        <p class="mt-1 text-sm">
                            • {{ $dependency->dependsOnTask?->title ?? '—' }}
                            ({{ $dependency->dependsOnTask?->status->label() ?? '—' }})
                        </p>
                    @empty
                        <flux:text class="mt-1">{{ __('Sin dependencias.') }}</flux:text>
                    @endforelse
                </div>

                <div>
                    <flux:heading size="sm">{{ __('Historial de estados y columnas') }}</flux:heading>
                    @forelse ($detail->statusTransitions as $transition)
                        <p class="mt-1 text-sm">
                            • {{ $transition->occurred_at->format('d/m/Y H:i') }} —
                            {{ $transition->fromColumnLabel() ?? __('Inicio') }}
                            → {{ $transition->toColumnLabel() }}
                            <span class="text-xs text-zinc-400">
                                ({{ $transition->from_status === null ? '—' : \App\TaskStatus::from($transition->from_status)->label() }}
                                → {{ \App\TaskStatus::from($transition->to_status)->label() }})
                            </span>
                            @if ($transition->changedBy)
                                <span class="text-xs text-zinc-400">
                                    · {{ $transition->changedBy->name }}
                                </span>
                            @endif
                        </p>
                    @empty
                        <flux:text class="mt-1">{{ __('Sin movimientos registrados.') }}</flux:text>
                    @endforelse
                </div>

                <flux:separator />

                <div>
                    <flux:heading size="sm">{{ __('Comentarios y evidencia') }}</flux:heading>

                    @forelse ($detail->comments as $comment)
                        @can('view', $comment)
                            <div class="mt-2 rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                    <span class="font-medium text-zinc-700 dark:text-zinc-200">
                                        {{ $comment->user?->name ?? __('Sistema') }}
                                    </span>
                                    <span>{{ $comment->created_at?->format('d/m/Y H:i') }}</span>
                                    @if ($comment->is_internal)
                                        <flux:badge size="sm" color="amber">{{ __('Interno') }}</flux:badge>
                                    @endif
                                </div>

                                @if ($comment->body !== '')
                                    <p class="mt-1 whitespace-pre-line break-words">{{ $comment->body }}</p>
                                @endif

                                <x-attachment-icons :groups="$this->attachmentGroups($comment->attachments)" />
                            </div>
                        @endcan
                    @empty
                        <flux:text class="mt-1">{{ __('Sin comentarios todavía.') }}</flux:text>
                    @endforelse

                    <x-attachment-icons :groups="$this->attachmentGroups($detail->attachments)" />

                    @if ($this->canCommentOn($detail))
                        <form wire:submit="addComment" class="mt-3 flex flex-col gap-3">
                            <flux:textarea
                                wire:model="commentBody"
                                rows="3"
                                :placeholder="__('Escribe un comentario o notas de la evidencia.')"
                            />
                            @error('commentBody')
                                <flux:text class="text-xs text-red-600">{{ $message }}</flux:text>
                            @enderror

                            <flux:field>
                                <flux:label>{{ __('Capturas, fotos o archivos (opcional)') }}</flux:label>
                                <input
                                    type="file"
                                    wire:model="commentFiles"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.doc,.docx,.xls,.xlsx"
                                    class="block w-full text-xs text-zinc-600 file:mr-3 file:rounded file:border-0 file:bg-zinc-200 file:px-3 file:py-1 file:text-xs dark:text-zinc-300 dark:file:bg-zinc-700"
                                />
                                @error('commentFiles')
                                    <flux:text class="text-xs text-red-600">{{ $message }}</flux:text>
                                @enderror
                                @error('commentFiles.*')
                                    <flux:text class="text-xs text-red-600">{{ $message }}</flux:text>
                                @enderror
                            </flux:field>

                            @can('comments.viewInternal')
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="commentIsInternal" class="rounded border-zinc-300" />
                                    {{ __('Comentario interno (solo jefes)') }}
                                </label>
                            @endcan

                            <div>
                                <flux:button type="submit" variant="primary" size="sm" wire:loading.attr="disabled">
                                    {{ __('Publicar comentario') }}
                                </flux:button>
                            </div>
                        </form>
                    @endif
                </div>

                <div class="flex justify-end gap-2">
                    @can('update', $detail)
                        <flux:button variant="ghost" wire:click="openEdit({{ $detail->id }})">
                            {{ __('Editar tarea') }}
                        </flux:button>
                    @endcan
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cerrar') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
