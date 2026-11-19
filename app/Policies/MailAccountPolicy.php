<?php

namespace App\Policies;

use App\Models\MailAccount;
use App\Models\User;

class MailAccountPolicy
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
        return $user->can('mail.accounts.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MailAccount $account): bool
    {
        return $user->can('mail.accounts.view') || $account->owner_user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('mail.accounts.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MailAccount $account): bool
    {
        return $user->can('mail.accounts.update') || $account->owner_user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MailAccount $account): bool
    {
        return $user->can('mail.accounts.delete');
    }

    /**
     * Determine whether the user can trigger a synchronization.
     */
    public function sync(User $user, MailAccount $account): bool
    {
        return $user->can('mail.sync.run') || $this->update($user, $account);
    }
}
