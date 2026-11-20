<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Grant every ability to the super administrator.
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    /**
     * Determine whether the user can view the list of users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.viewAny');
    }

    /**
     * Determine whether the user can view a user.
     */
    public function view(User $user, User $target): bool
    {
        return $user->can('users.viewAny');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can update users.
     */
    public function update(User $user, User $target): bool
    {
        return $user->can('users.update');
    }

    /**
     * Determine whether the user can delete users.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete') && $user->id !== $target->id;
    }
}
