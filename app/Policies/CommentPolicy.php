<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;

class CommentPolicy
{
    /**
     * Grant every ability to the super administrator.
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    /**
     * Determine whether the user can view the comment.
     */
    public function view(User $user, Comment $comment): bool
    {
        if (! $user->can('view', $comment->commentable)) {
            return false;
        }

        return ! $comment->is_internal
            || $user->can('comments.viewInternal')
            || $this->managesCommentableArea($user, $comment);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?Comment $comment = null): bool
    {
        if (! $user->can('comments.create')) {
            return false;
        }

        return $comment === null || $user->can('view', $comment->commentable);
    }

    /**
     * Determine whether the user can update the comment.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id
            || ($user->can('comments.update') && $user->can('view', $comment->commentable));
    }

    /**
     * Determine whether the user can delete the comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id
            || ($user->can('comments.delete') && $user->can('view', $comment->commentable));
    }

    /**
     * Determine whether the user manages the area of the commented resource.
     */
    private function managesCommentableArea(User $user, Comment $comment): bool
    {
        $areaId = match (true) {
            $comment->commentable instanceof Ticket => $comment->commentable->area_id,
            $comment->commentable instanceof Task => $comment->commentable->area_id,
            default => null,
        };

        return $areaId !== null && $user->managedAreas()->whereKey($areaId)->exists();
    }
}
