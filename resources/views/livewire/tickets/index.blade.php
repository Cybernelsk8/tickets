<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Tickets') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Listado y asignación de tickets.') }}</flux:text>
        </div>
    </div>

    @if ($assigningTicketId !== null)
        <flux:card>
            <flux:heading size="lg">{{ __('Asignar ticket') }}</flux:heading>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Área') }}</flux:label>
                    <flux:select wire:model.live="areaId">
                        <flux:select.option value="">{{ __('Selecciona un área') }}</flux:select.option>
                        @foreach ($this->areas as $area)
                            <flux:select.option :value="(string) $area->id" wire:key="ticket-area-{{ $area->id }}">
                                {{ $area->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="areaId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Categoría') }}</flux:label>
                    <flux:select wire:model="categoryId">
                        <flux:select.option value="">{{ __('Selecciona una categoría') }}</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option :value="(string) $category->id" wire:key="ticket-category-{{ $category->id }}">
                                {{ $category->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="categoryId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Prioridad') }}</flux:label>
                    <flux:select wire:model="priority">
                        @foreach (\App\TicketPriority::cases() as $case)
                            <flux:select.option :value="$case->value" wire:key="ticket-priority-{{ $case->value }}">
                                {{ $case->label() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="priority" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Responsable') }}</flux:label>
                    <flux:select wire:model="assigneeId">
                        <flux:select.option value="">{{ __('Sin responsable') }}</flux:select.option>
                        @foreach ($this->assignees as $assignee)
                            <flux:select.option :value="(string) $assignee->id" wire:key="ticket-assignee-{{ $assignee->id }}">
                                {{ $assignee->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="assigneeId" />
                </flux:field>
            </div>

            <div class="mt-4 flex gap-2">
                <flux:button variant="primary" wire:click="assign" wire:loading.attr="disabled">
                    {{ __('Asignar') }}
                </flux:button>
                <flux:button variant="ghost" wire:click="$set('assigningTicketId', null)">
                    {{ __('Cancelar') }}
                </flux:button>
            </div>
        </flux:card>
    @endif

    <flux:card>
        <flux:input wire:model.live="search" :label="__('Buscar')" :placeholder="__('Código, asunto o correo del remitente')" />

        <div class="mt-4">
            @if ($this->tickets->isEmpty())
                <flux:text>{{ __('No hay tickets registrados.') }}</flux:text>
            @else
                <flux:table :paginate="$this->tickets">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Código') }}</flux:table.column>
                        <flux:table.column>{{ __('Asunto') }}</flux:table.column>
                        <flux:table.column>{{ __('Remitente') }}</flux:table.column>
                        <flux:table.column>{{ __('Área') }}</flux:table.column>
                        <flux:table.column>{{ __('Prioridad') }}</flux:table.column>
                        <flux:table.column>{{ __('Estado') }}</flux:table.column>
                        <flux:table.column>{{ __('Responsable') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->tickets as $ticket)
                            <flux:table.row wire:key="ticket-row-{{ $ticket->id }}">
                                <flux:table.cell>{{ $ticket->code }}</flux:table.cell>
                                <flux:table.cell>{{ $ticket->subject }}</flux:table.cell>
                                <flux:table.cell>{{ $ticket->requester_email ?: $ticket->requester_name }}</flux:table.cell>
                                <flux:table.cell>{{ $ticket->area?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $ticket->priority->label() }}</flux:table.cell>
                                <flux:table.cell><flux:badge>{{ $ticket->status->label() }}</flux:badge></flux:table.cell>
                                <flux:table.cell>{{ $ticket->assignee?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @can('assign', $ticket)
                                        <flux:button size="sm" variant="ghost" wire:click="openAssign({{ $ticket->id }})">
                                            {{ __('Asignar') }}
                                        </flux:button>
                                    @endcan
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </flux:card>
</div>
