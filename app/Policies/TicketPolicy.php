<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * What a member may do with a support ticket, on the `web` guard.
 *
 * Ownership is `user_id`, a column on the row, so every method here decides
 * from attributes already loaded and runs no query — safe to ask per row
 * (.ai/rules/policies.md). A ticket whose account has been deleted has no
 * owner and is reachable by nobody on this side; the team still sees it in
 * Nova.
 *
 * There is no `create`: any signed-in member may open a ticket and there is
 * no model to govern yet, and no `viewAny`: the index lists the caller's own
 * rows only.
 *
 * Admins never reach this policy — they act through App\Nova\Policies and
 * the `admin` guard.
 */
class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $this->owns($user, $ticket);
    }

    /**
     * A member may write to their own ticket while it is still open or
     * answered; a closed one takes a new ticket, not a reopened thread.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->owns($user, $ticket) && ! $ticket->isClosed();
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return $this->owns($user, $ticket) && ! $ticket->isClosed();
    }

    private function owns(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id !== null && $ticket->user_id === $user->getKey();
    }
}
