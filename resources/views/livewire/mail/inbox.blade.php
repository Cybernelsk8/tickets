<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Bandeja de correo') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Correos importados, hilos y adjuntos privados.') }}</flux:text>
        </div>

        @can('mail.accounts.view')
            <flux:button variant="ghost" :href="route('mail.accounts.index')" wire:navigate icon="cog">
                {{ __('Cuentas de correo') }}
            </flux:button>
        @endcan
    </div>

    <flux:card>
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_auto_auto] md:items-end">
            <flux:field>
                <flux:label>{{ __('Cuenta') }}</flux:label>
                <flux:select wire:model.live="selectedAccountId">
                    @foreach ($this->accounts as $account)
                        <flux:select.option :value="(string) $account->id" wire:key="inbox-account-{{ $account->id }}">
                            {{ $account->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Buscar') }}</flux:label>
                <flux:input wire:model.live="search" :placeholder="__('Asunto, remitente o correo')" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model.live="unreadOnly" />
                <flux:label>{{ __('Solo no leídos') }}</flux:label>
            </flux:field>

            @if ($this->selectedAccount !== null)
                @can('sync', $this->selectedAccount)
                    <flux:button wire:click="syncSelected" wire:loading.attr="disabled" icon="arrow-path">
                        {{ __('Sincronizar') }}
                    </flux:button>
                @endcan
            @endif
        </div>
    </flux:card>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <flux:card>
            @if ($this->accounts->isEmpty())
                <flux:text>{{ __('No hay cuentas activas disponibles. Configura una cuenta para ver los correos.') }}</flux:text>
            @else
                <flux:table :paginate="$this->messages">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Asunto') }}</flux:table.column>
                        <flux:table.column>{{ __('Remitente') }}</flux:table.column>
                        <flux:table.column>{{ __('Recibido') }}</flux:table.column>
                        <flux:table.column>{{ __('Hilo') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->messages as $message)
                            <flux:table.row wire:key="inbox-message-{{ $message->id }}" @class(['bg-zinc-100' => $selectedMessageId === $message->id])>
                                <flux:table.cell>
                                    <flux:button variant="ghost" wire:click="selectMessage({{ $message->id }})">
                                        @if (! $message->is_read)
                                            <span class="font-semibold">●</span>
                                        @endif
                                        {{ $message->subject }}
                                    </flux:button>
                                </flux:table.cell>
                                <flux:table.cell>{{ $message->from_name ?: $message->from_email }}</flux:table.cell>
                                <flux:table.cell>{{ $message->received_at?->format('d/m/Y H:i') }}</flux:table.cell>
                                <flux:table.cell>{{ $message->replies_count }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <flux:card>
            @if ($this->selectedMessage === null)
                <flux:heading size="lg">{{ __('Detalle') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Selecciona un correo para verlo.') }}</flux:text>
            @else
                @php($message = $this->selectedMessage)

                <flux:heading size="lg">{{ $message->subject }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $message->from_name ?: $message->from_email }} &lt;{{ $message->from_email }}&gt;
                </flux:text>
                <flux:text size="sm" class="mt-1">
                    {{ $message->received_at?->format('d/m/Y H:i') }} · {{ $message->folder }}
                </flux:text>

                <div class="mt-4">
                    <flux:heading size="sm">{{ __('Mensaje') }}</flux:heading>
                    <flux:text class="mt-1 whitespace-pre-wrap break-words">
                        {{ $message->body_text ?: __('Este correo no tiene cuerpo en texto plano.') }}
                    </flux:text>
                </div>

                @if ($message->replies->isNotEmpty())
                    <div class="mt-4">
                        <flux:heading size="sm">{{ __('Respuestas del hilo') }}</flux:heading>
                        <div class="mt-2 grid gap-2">
                            @foreach ($message->replies->sortBy('received_at') as $reply)
                                <div wire:key="inbox-reply-{{ $reply->id }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <flux:text size="sm" class="font-medium">{{ $reply->subject }}</flux:text>
                                    <flux:text size="sm" class="whitespace-pre-wrap break-words">{{ $reply->body_text }}</flux:text>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-4">
                    <flux:heading size="sm">{{ __('Adjuntos') }}</flux:heading>

                    @if ($message->attachments->isEmpty() && $message->replies->every(fn ($reply) => $reply->attachments->isEmpty()))
                        <flux:text size="sm" class="mt-1">{{ __('Sin adjuntos.') }}</flux:text>
                    @else
                        <div class="mt-2 grid gap-2">
                            @foreach ($message->attachments as $attachment)
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    :href="route('attachments.download', $attachment)"
                                    icon="paper-clip"
                                    wire:key="inbox-attachment-{{ $attachment->id }}"
                                >
                                    {{ $attachment->original_name }} ({{ number_format($attachment->size_bytes / 1024, 1) }} KB)
                                </flux:button>
                            @endforeach

                            @foreach ($message->replies as $reply)
                                @foreach ($reply->attachments as $attachment)
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        :href="route('attachments.download', $attachment)"
                                        icon="paper-clip"
                                        wire:key="inbox-reply-attachment-{{ $attachment->id }}"
                                    >
                                        {{ $attachment->original_name }} ({{ number_format($attachment->size_bytes / 1024, 1) }} KB)
                                    </flux:button>
                                @endforeach
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @can('create', \App\Models\Ticket::class)
                        @if ($this->selectedTicket === null)
                            <flux:button variant="primary" icon="ticket" wire:click="createTicket" wire:loading.attr="disabled">
                                {{ __('Crear ticket') }}
                            </flux:button>
                        @else
                            <flux:badge>{{ __('Ticket') }} {{ $this->selectedTicket->code }}</flux:badge>
                            <flux:button variant="ghost" :href="route('tickets.index')" wire:navigate>
                                {{ __('Ver tickets') }}
                            </flux:button>
                        @endif
                    @endcan
                </div>
            @endif
        </flux:card>
    </div>
</div>
