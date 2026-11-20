<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Usuarios') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Crea usuarios y asígnale roles y permisos directos.') }}</flux:text>
        </div>

        @can('create', App\Models\User::class)
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                {{ __('Nuevo usuario') }}
            </flux:button>
        @endcan
    </div>

    <flux:error name="user" />

    <flux:card>
        <flux:input wire:model.live="search" :label="__('Buscar')" :placeholder="__('Nombre o correo electrónico')" />

        <div class="mt-4">
            @if ($this->users->isEmpty())
                <flux:text>{{ __('No hay usuarios registrados.') }}</flux:text>
            @else
                <flux:table :paginate="$this->users">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Usuario') }}</flux:table.column>
                        <flux:table.column>{{ __('Correo') }}</flux:table.column>
                        <flux:table.column>{{ __('Área') }}</flux:table.column>
                        <flux:table.column>{{ __('Roles') }}</flux:table.column>
                        <flux:table.column>{{ __('Permisos directos') }}</flux:table.column>
                        <flux:table.column>{{ __('Estado') }}</flux:table.column>
                        <flux:table.column>{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->users as $user)
                            <flux:table.row wire:key="admin-user-{{ $user->id }}">
                                <flux:table.cell>{{ $user->name }}</flux:table.cell>
                                <flux:table.cell>{{ $user->email }}</flux:table.cell>
                                <flux:table.cell>{{ $user->area?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($user->roles as $role)
                                            <flux:badge size="sm" :color="$role->name === 'super_admin' ? 'red' : 'zinc'">
                                                {{ $role->name }}
                                            </flux:badge>
                                        @empty
                                            <span class="text-zinc-400">—</span>
                                        @endforelse
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $user->permissions->count() }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$user->is_active ? 'green' : 'zinc'">
                                        {{ $user->is_active ? __('Activo') : __('Inactivo') }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-2">
                                        @can('update', $user)
                                            <flux:button size="sm" wire:click="openEdit({{ $user->id }})">
                                                {{ __('Editar') }}
                                            </flux:button>
                                        @endcan

                                        @can('delete', $user)
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="delete({{ $user->id }})"
                                                wire:confirm="{{ __('¿Eliminar este usuario?') }}"
                                            >
                                                {{ __('Eliminar') }}
                                            </flux:button>
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </flux:card>

    <flux:modal wire:model.self="showForm" name="user-form" class="md:w-3xl">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">
                {{ $editingUserId === null ? __('Nuevo usuario') : __('Editar usuario') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Nombre') }}</flux:label>
                    <flux:input wire:model="name" autocomplete="off" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Correo electrónico') }}</flux:label>
                    <flux:input wire:model="email" type="email" autocomplete="off" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Contraseña') }}</flux:label>
                    <flux:input wire:model="password" type="password" autocomplete="new-password" />
                    <flux:error name="password" />
                    @if ($editingUserId !== null)
                        <flux:text size="sm" class="mt-1">{{ __('Déjala vacía para conservar la actual.') }}</flux:text>
                    @endif
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Área') }}</flux:label>
                    <flux:select wire:model="areaId">
                        <flux:select.option value="">{{ __('Sin área') }}</flux:select.option>
                        @foreach ($this->areas as $area)
                            <flux:select.option :value="(string) $area->id" wire:key="user-area-{{ $area->id }}">
                                {{ $area->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="areaId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Puesto') }}</flux:label>
                    <flux:input wire:model="jobTitle" autocomplete="off" />
                    <flux:error name="jobTitle" />
                </flux:field>

                <flux:field variant="inline">
                    <flux:checkbox wire:model="isActive" />
                    <flux:label>{{ __('Usuario activo') }}</flux:label>
                </flux:field>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Roles') }}</flux:label>
                    <flux:checkbox.group wire:model="selectedRoles">
                        @foreach ($this->roles as $role)
                            <flux:checkbox :label="$role->name" :value="$role->name" wire:key="user-role-{{ $role->id }}" />
                        @endforeach
                    </flux:checkbox.group>
                    <flux:error name="selectedRoles" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Permisos directos') }}</flux:label>
                    <flux:checkbox.group wire:model="selectedPermissions">
                        @foreach ($this->permissions as $permission)
                            <flux:checkbox :label="$permission->name" :value="$permission->name" wire:key="user-permission-{{ $permission->id }}" />
                        @endforeach
                    </flux:checkbox.group>
                    <flux:error name="selectedPermissions" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Guardar') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
