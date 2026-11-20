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
    </div>

    @error('board')
        <flux:callout variant="danger" :heading="$message" />
    @enderror

    @if ($this->areas->isEmpty())
        <flux:card>
            <flux:text>{{ __('No tienes áreas disponibles para mostrar un tablero.') }}</flux:text>
        </flux:card>
    @else
        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($this->columns as $column)
                <flux:card class="min-h-[24rem]">
                    <div class="flex items-center justify-between gap-2">
                        <flux:heading size="sm">
                            {{ $column['label'] }}
                            <span class="text-zinc-400">({{ $column['tasks']->count() }})</span>
                        </flux:heading>

                        @can('create', \App\Models\Task::class)
                            <flux:button
                                size="xs"
                                variant="ghost"
                                wire:click="openCreate('{{ $column['id'] }}')"
                                wire:sort:ignore
                            >
                                {{ __('+ Nueva') }}
                            </flux:button>
                        @endcan
                    </div>

                    <div
                        class="mt-3 flex min-h-[18rem] flex-col gap-2"
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
                            @endphp
                            <div
                                wire:key="board-task-{{ $task->id }}"
                                wire:sort:item="{{ $task->id }}"
                                class="rounded-lg border border-zinc-200 border-l-4 bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
                                style="border-left-color: {{ $areaColor }}"
                            >
                                <div class="flex gap-2">
                                    <div
                                        wire:sort:handle
                                        class="cursor-grab select-none px-1 text-zinc-400"
                                        title="{{ __('Arrastrar para reordenar') }}"
                                    >
                                        ⠿
                                    </div>

                                    <div wire:sort:ignore class="min-w-0 flex-1">
                                        <p class="break-words text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                            {{ $task->title }}
                                        </p>

                                        <p class="mt-1 text-xs font-medium" style="color: {{ $areaColor }}">
                                            {{ $task->area?->name ?? __('Sin área') }}
                                        </p>

                                        <div class="mt-1 flex flex-wrap items-center gap-1 text-xs text-zinc-500">
                                            @if ($task->ticket)
                                                <flux:badge size="sm">{{ $task->ticket->code }}</flux:badge>
                                            @else
                                                <flux:badge size="sm" color="zinc">{{ __('Tablero') }}</flux:badge>
                                            @endif

                                            @if ($task->parent)
                                                <flux:badge size="sm" color="amber">{{ __('Subtarea') }}</flux:badge>
                                            @endif

                                            @if ($task->status === \App\TaskStatus::Blocked)
                                                <flux:badge size="sm" color="rose">{{ __('Bloqueado') }}</flux:badge>
                                            @endif

                                            <flux:badge size="sm">{{ $task->priority->label() }}</flux:badge>

                                            @if ($task->due_at)
                                                <span>{{ $task->due_at->format('d/m/Y') }}</span>
                                            @endif
                                        </div>

                                        <p class="mt-2 text-xs text-zinc-500">
                                            {{ $task->assignee?->name ?? __('Sin responsable') }}
                                        </p>

                                        @if ($movement)
                                            <p class="mt-1 text-xs text-sky-600 dark:text-sky-400">
                                                {{ __('Movida') }}:
                                                {{ $movement->fromColumnLabel() ?? __('Inicio') }}
                                                → {{ $movement->toColumnLabel() }}
                                                <span class="text-zinc-400">
                                                    {{ $movement->occurred_at->format('d/m/Y H:i') }}
                                                </span>
                                            </p>
                                        @endif

                                        @if ($task->dependencies->contains(fn ($dependency) => $dependency->dependsOnTask?->status !== \App\TaskStatus::Done))
                                            <p class="mt-1 text-xs text-amber-600">
                                                {{ __('Dependencia pendiente') }}
                                            </p>
                                        @endif

                                        <div class="mt-2 flex flex-wrap gap-1">
                                            <flux:button size="xs" variant="ghost" wire:click="openDetails({{ $task->id }})">
                                                {{ __('Detalle') }}
                                            </flux:button>

                                            @can('createSubtask', $task)
                                                <flux:button size="xs" variant="ghost" wire:click="openSubtask({{ $task->id }})">
                                                    {{ __('Subtarea') }}
                                                </flux:button>
                                            @endcan

                                            @can('update', $task)
                                                <flux:button size="xs" variant="ghost" wire:click="openEdit({{ $task->id }})">
                                                    {{ __('Editar') }}
                                                </flux:button>
                                            @endcan

                                            @foreach (\App\TaskStatus::cases() as $status)
                                                @if ($task->status->canTransitionTo($status))
                                                    <flux:button
                                                        size="xs"
                                                        variant="ghost"
                                                        wire:click="moveTask({{ $task->id }}, '{{ $status->value }}')"
                                                    >
                                                        {{ $status->label() }}
                                                    </flux:button>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-center text-xs text-zinc-400 dark:border-zinc-700">
                                {{ __('Suelta tareas aquí') }}
                            </div>
                        @endforelse
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    <flux:modal wire:model.self="showCreateModal" name="create-task" class="md:w-2xl">
        @php($canChooseAssignee = $this->canChooseAssignee())

        <form wire:submit="createTask" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ $canChooseAssignee ? __('Nueva tarea') : __('Nueva subtarea') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $canChooseAssignee
                        ? __('Puedes crear una tarea independiente o un desglose de otra tarea.')
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
                @if ($canChooseAssignee)
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
                    {{ $canChooseAssignee ? __('Crear tarea') : __('Crear subtarea') }}
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

                                @foreach ($comment->attachments as $attachment)
                                    <a
                                        class="mt-1 block text-xs text-blue-600 underline dark:text-blue-400"
                                        href="{{ route('attachments.download', $attachment) }}"
                                        wire:navigate
                                    >
                                        {{ $attachment->original_name }}
                                    </a>
                                @endforeach
                            </div>
                        @endcan
                    @empty
                        <flux:text class="mt-1">{{ __('Sin comentarios todavía.') }}</flux:text>
                    @endforelse

                    @foreach ($detail->attachments as $attachment)
                        <a
                            class="mt-1 block text-xs text-blue-600 underline dark:text-blue-400"
                            href="{{ route('attachments.download', $attachment) }}"
                            wire:navigate
                        >
                            {{ $attachment->original_name }}
                        </a>
                    @endforeach

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
