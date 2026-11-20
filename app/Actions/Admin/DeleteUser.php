<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    /**
     * Delete a user, protecting the current session and super administrators.
     */
    public function handle(User $user): void
    {
        Gate::authorize('delete', $user);

        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'user' => __('No puedes eliminar tu propio usuario.'),
            ]);
        }

        if ($user->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'user' => __('No se puede eliminar un superadministrador.'),
            ]);
        }

        $user->delete();
    }
}
