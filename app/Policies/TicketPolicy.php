<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
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
        return $user->can('tickets.viewAny') || $user->can('tickets.viewOwnArea');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.viewAny')
            || $ticket->assignee_id === $user->id
            || $ticket->created_by === $user->id
            || ($ticket->area_id !== null && $user->managedAreas()->whereKey($ticket->area_id)->exists())
            || $this->belongsToUserArea($user, $ticket->area_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tickets.create');
    }

    /**
     * Determine whether the user can assign the ticket.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        if ($ticket->status->isFinal()) {
            return false;
        }

        return $user->can('tickets.assign') && $this->canManageArea($user, $ticket->area_id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.update') && $this->canManageArea($user, $ticket->area_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.delete');
    }

    /**
     * Determine whether the user may manage tickets in the given area.
     */
    private function canManageArea(User $user, ?int $areaId): bool
    {
        if ($areaId === null) {
            return $user->can('tickets.viewAny');
        }

        return $user->can('tickets.viewAny')
            || $user->managedAreas()->whereKey($areaId)->exists();
    }

    /**
     * Determine whether the ticket area matches the user's own area.
     */
    private function belongsToUserArea(User $user, ?int $areaId): bool
    {
        return $areaId !== null && $user->area_id === $areaId;
    }
}
