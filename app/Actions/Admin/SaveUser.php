<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class SaveUser
{
    /**
     * Create or update a user together with their roles and direct permissions.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $user = null): User
    {
        Gate::authorize($user === null ? 'create' : 'update', $user ?? User::class);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user === null ? 'required' : 'nullable', 'string', 'min:8'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ])->validate();

        $roles = $validated['roles'] ?? [];

        if ($user?->hasRole('super_admin') && ! in_array('super_admin', $roles, true)) {
            $roles[] = 'super_admin';
        }

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'area_id' => $validated['area_id'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];

        if (! empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        if ($user === null) {
            $attributes['email_verified_at'] = now();
            $user = User::create($attributes);
        } else {
            $user->update($attributes);
        }

        $user->syncRoles($roles);
        $user->syncPermissions($validated['permissions'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->load(['roles', 'permissions']);
    }
}
