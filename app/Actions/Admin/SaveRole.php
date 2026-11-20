<?php

namespace App\Actions\Admin;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SaveRole
{
    /**
     * Create or update a role and synchronize its permissions.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Role $role = null): Role
    {
        Gate::authorize($role === null ? 'create' : 'update', $role ?? Role::class);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ])->validate();

        if ($role?->name === 'super_admin' && $validated['name'] !== 'super_admin') {
            throw ValidationException::withMessages([
                'name' => __('El rol super_admin no se puede renombrar.'),
            ]);
        }

        if ($role === null) {
            $role = Role::create(['name' => $validated['name']]);
        } else {
            $role->update(['name' => $validated['name']]);
        }

        $role->syncPermissions($validated['permissions'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->load('permissions');
    }
}
