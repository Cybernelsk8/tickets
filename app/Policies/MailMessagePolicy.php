<?php

namespace App\Policies;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;

class MailMessagePolicy
{
    /**
     * Grant every ability to the super administrator.
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    /**
     * Determine whether the user can view any messages.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny', MailAccount::class);
    }

    /**
     * Determine whether the user can view a message through its mail account.
     */
    public function view(User $user, MailMessage $message): bool
    {
        return $user->can('view', $message->account);
    }
}
