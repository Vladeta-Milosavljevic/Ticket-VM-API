<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create tickets.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin()
            || $ticket->manager_id === $user->id
            || $ticket->agent_id === $user->id;
    }

    /**
     * Determine whether the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Determine whether the user can assign an agent to the ticket.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $ticket->manager_id === $user->id;
    }

    /**
     * Determine whether the user can mark the ticket as complete.
     */
    public function complete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $ticket->agent_id === $user->id;
    }

    /**
     * Determine whether the user can approve the ticket completion.
     */
    public function approve(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $ticket->manager_id === $user->id;
    }

    /**
     * Determine whether the user can reject the ticket completion.
     */
    public function reject(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $ticket->manager_id === $user->id;
    }

    /**
     * Determine whether the user can view comments on the ticket.
     */
    public function viewComments(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin()
            || $ticket->manager_id === $user->id
            || $ticket->agent_id === $user->id;
    }

    /**
     * Determine whether the user can create comments on the ticket.
     */
    public function createComment(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin()
            || $ticket->manager_id === $user->id
            || $ticket->agent_id === $user->id;
    }
}
