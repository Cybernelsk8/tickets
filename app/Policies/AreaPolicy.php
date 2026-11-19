<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

class AreaPolicy
{
    /**
     * Grant every ability to the super administrator.
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('areas.viewAny')
            || $user->can('tickets.viewAny')
            || $user->can('tickets.viewOwnArea')
            || $user->can('tasks.viewAny');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Area $area): bool
    {
        return $this->viewAny($user)
            || $area->manager_id === $user->id
            || $area->users()->whereKey($user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('areas.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Area $area): bool
    {
        return $user->can('areas.update') || $area->manager_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Area $area): bool
    {
        return $user->can('areas.delete');
    }
}
