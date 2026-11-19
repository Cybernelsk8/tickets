<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Grant every ability to the super administrator.
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    /**
     * Determine whether the user can view the attachment.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        return $user->can('attachments.view')
            || $attachment->uploaded_by === $user->id
            || $user->can('view', $attachment->attachable);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('attachments.create');
    }

    /**
     * Determine whether the user can delete the attachment.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        return $attachment->uploaded_by === $user->id
            || $user->can('attachments.delete');
    }
}
