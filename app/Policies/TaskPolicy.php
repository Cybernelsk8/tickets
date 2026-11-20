<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
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
        return $user->can('tasks.viewAny')
            || $user->can('tickets.viewAny')
            || $user->can('tickets.viewOwnArea');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Task $task): bool
    {
        return $this->canManageTask($user, $task);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tasks.create')
            && ($user->can('tickets.viewAny') || $user->managedAreas()->exists());
    }

    /**
     * Determine whether the user can split a task they own into subtasks.
     */
    public function createSubtask(User $user, Task $parent): bool
    {
        return $user->can('tasks.update')
            && $parent->area_id !== null
            && ($user->area_id === $parent->area_id || $this->managesTaskArea($user, $parent))
            && $this->canManageTask($user, $parent);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Task $task): bool
    {
        return $user->can('tasks.update') && $this->canManageTask($user, $task);
    }

    /**
     * Determine whether the user can change the task's responsible user.
     */
    public function reassign(User $user, Task $task): bool
    {
        return $user->can('tickets.viewAny')
            || ($task->area_id !== null && $user->managedAreas()->whereKey($task->area_id)->exists());
    }

    /**
     * Determine whether the user can move the task in the Kanban board.
     */
    public function move(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        return $user->can('tasks.delete')
            && ($user->can('tickets.viewAny') || $this->managesTaskArea($user, $task));
    }

    /**
     * Determine whether the user may act on the given task.
     */
    private function canManageTask(User $user, Task $task): bool
    {
        return $task->assignee_id === $user->id
            || $task->created_by === $user->id
            || $user->can('tickets.viewAny')
            || $this->managesTaskArea($user, $task);
    }

    /**
     * Determine whether the user manages the task's area.
     */
    private function managesTaskArea(User $user, Task $task): bool
    {
        return $task->area_id !== null
            && $user->managedAreas()->whereKey($task->area_id)->exists();
    }
}
