<?php

namespace App\Actions\Admin;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DeleteRole
{
    /**
     * Delete a role when it is not protected and has no users assigned.
     */
    public function handle(Role $role): void
    {
        Gate::authorize('delete', $role);

        if ($role->name === 'super_admin') {
            throw ValidationException::withMessages([
                'role' => __('El rol super_admin no se puede eliminar.'),
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => __('El rol todavía tiene usuarios asignados.'),
            ]);
        }

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
