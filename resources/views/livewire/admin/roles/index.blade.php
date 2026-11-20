<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Roles y permisos') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Crea roles y define los permisos que otorga cada uno.') }}</flux:text>
        </div>

        @can('create', Spatie\Permission\Models\Role::class)
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                {{ __('Nuevo rol') }}
            </flux:button>
        @endcan
    </div>

    <flux:error name="role" />

    <flux:card>
        <flux:input wire:model.live="search" :label="__('Buscar')" :placeholder="__('Nombre del rol')" />

        <div class="mt-4">
            @if ($this->roles->isEmpty())
                <flux:text>{{ __('No hay roles registrados.') }}</flux:text>
            @else
                <flux:table :paginate="$this->roles">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Rol') }}</flux:table.column>
                        <flux:table.column>{{ __('Permisos') }}</flux:table.column>
                        <flux:table.column>{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->roles as $role)
                            <flux:table.row wire:key="admin-role-{{ $role->id }}">
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        {{ $role->name }}
                                        @if ($role->name === 'super_admin')
                                            <flux:badge size="sm" color="red">{{ __('Protegido') }}</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $role->permissions->count() }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-2">
                                        @can('update', $role)
                                            <flux:button size="sm" wire:click="openEdit({{ $role->id }})">
                                                {{ __('Editar') }}
                                            </flux:button>
                                        @endcan

                                        @can('delete', $role)
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="delete({{ $role->id }})"
                                                wire:confirm="{{ __('¿Eliminar este rol?') }}"
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

    <flux:modal wire:model.self="showForm" name="role-form" class="md:w-2xl">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">
                {{ $editingRoleId === null ? __('Nuevo rol') : __('Editar rol') }}
            </flux:heading>

            <flux:field>
                <flux:label>{{ __('Nombre') }}</flux:label>
                <flux:input wire:model="name" autocomplete="off" :disabled="$editingRoleId !== null && $name === 'super_admin'" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Permisos') }}</flux:label>
                <flux:checkbox.group wire:model="selectedPermissions">
                    @foreach ($this->permissions as $permission)
                        <flux:checkbox :label="$permission->name" :value="$permission->name" wire:key="role-permission-{{ $permission->id }}" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="selectedPermissions" />
            </flux:field>

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
