<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Cuentas de correo') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Configura IMAP/SMTP, prueba la conexión y encola la sincronización.') }}</flux:text>
        </div>

        <flux:button variant="ghost" :href="route('mail.inbox')" wire:navigate icon="inbox">
            {{ __('Ir a la bandeja') }}
        </flux:button>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card>
            <flux:heading size="lg">
                {{ $accountId === null ? __('Nueva cuenta') : __('Editar cuenta') }}
            </flux:heading>

            <form class="mt-4 grid gap-4 md:grid-cols-2" wire:submit="save">
                <flux:field class="md:col-span-2">
                    <flux:label>{{ __('Nombre') }}</flux:label>
                    <flux:input wire:model="name" autocomplete="off" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Correo') }}</flux:label>
                    <flux:input wire:model="email" type="email" autocomplete="off" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Usuario IMAP') }}</flux:label>
                    <flux:input wire:model="username" autocomplete="off" />
                    <flux:error name="username" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Contraseña') }}</flux:label>
                    <flux:input wire:model="password" type="password" autocomplete="new-password" />
                    <flux:error name="password" />
                    @if ($accountId !== null)
                        <flux:text size="sm" class="mt-1">{{ __('Déjala vacía para conservar la actual.') }}</flux:text>
                    @endif
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Propietario') }}</flux:label>
                    <flux:select wire:model="ownerUserId">
                        <flux:select.option value="">{{ __('Ninguno') }}</flux:select.option>
                        @foreach ($this->users as $user)
                            <flux:select.option :value="(string) $user->id" wire:key="owner-{{ $user->id }}">
                                {{ $user->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="ownerUserId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Servidor IMAP') }}</flux:label>
                    <flux:input wire:model="imapHost" autocomplete="off" />
                    <flux:error name="imapHost" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Puerto IMAP') }}</flux:label>
                    <flux:input wire:model="imapPort" type="number" />
                    <flux:error name="imapPort" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Cifrado IMAP') }}</flux:label>
                    <flux:select wire:model="imapEncryption">
                        <flux:select.option value="ssl">SSL</flux:select.option>
                        <flux:select.option value="tls">TLS</flux:select.option>
                        <flux:select.option value="none">{{ __('Ninguno') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="imapEncryption" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Servidor SMTP') }}</flux:label>
                    <flux:input wire:model="smtpHost" autocomplete="off" />
                    <flux:error name="smtpHost" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Puerto SMTP') }}</flux:label>
                    <flux:input wire:model="smtpPort" type="number" />
                    <flux:error name="smtpPort" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Cifrado SMTP') }}</flux:label>
                    <flux:select wire:model="smtpEncryption">
                        <flux:select.option value="tls">TLS</flux:select.option>
                        <flux:select.option value="ssl">SSL</flux:select.option>
                        <flux:select.option value="none">{{ __('Ninguno') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="smtpEncryption" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Sincronizar cada (minutos)') }}</flux:label>
                    <flux:input wire:model="syncEveryMinutes" type="number" min="1" />
                    <flux:error name="syncEveryMinutes" />
                </flux:field>

                <flux:field variant="inline" class="md:col-span-2">
                    <flux:checkbox wire:model="isActive" />
                    <flux:label>{{ __('Cuenta activa') }}</flux:label>
                </flux:field>

                <div class="flex flex-wrap gap-2 md:col-span-2">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        {{ $accountId === null ? __('Crear cuenta') : __('Guardar cambios') }}
                    </flux:button>

                    <flux:button type="button" wire:click="testConnection" wire:loading.attr="disabled">
                        {{ __('Probar conexión') }}
                    </flux:button>

                    @if ($accountId !== null)
                        <flux:button type="button" wire:click="resetForm">
                            {{ __('Cancelar') }}
                        </flux:button>
                    @endif
                </div>
            </form>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">{{ __('Cuentas configuradas') }}</flux:heading>

            @if ($this->accounts->isEmpty())
                <flux:text class="mt-4">{{ __('Todavía no hay cuentas de correo.') }}</flux:text>
            @else
                <div class="mt-4 grid gap-3">
                    @foreach ($this->accounts as $account)
                        <div wire:key="mail-account-{{ $account->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <flux:heading size="sm">{{ $account->name }}</flux:heading>
                                    <flux:text size="sm">{{ $account->email }} · {{ $account->imap_host }}</flux:text>
                                    <flux:text size="sm">
                                        {{ $account->messages_count }} {{ __('mensajes') }} ·
                                        {{ $account->is_active ? __('activa') : __('inactiva') }}
                                    </flux:text>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    @can('update', $account)
                                        <flux:button size="sm" wire:click="edit({{ $account->id }})">
                                            {{ __('Editar') }}
                                        </flux:button>
                                    @endcan

                                    @can('sync', $account)
                                        <flux:button size="sm" wire:click="syncNow({{ $account->id }})" wire:loading.attr="disabled">
                                            {{ __('Sincronizar') }}
                                        </flux:button>
                                    @endcan

                                    @can('delete', $account)
                                        <flux:button size="sm" variant="danger" wire:click="delete({{ $account->id }})" wire:confirm="{{ __('¿Eliminar esta cuenta?') }}">
                                            {{ __('Eliminar') }}
                                        </flux:button>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</div>
