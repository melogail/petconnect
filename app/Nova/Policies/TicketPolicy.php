<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\Ticket as TicketResource;

/**
 * Authorization for the Ticket resource, on the `admin` guard.
 *
 * A ticket is what a visitor wrote to the team. An admin reads it and works it
 * through the two actions (Actions\ReplyToTicket, Actions\ChangeTicketStatus);
 * they do not author one and they do not edit one, so `create` and `update`
 * are false and App\Nova\Ticket exposes no writable field. `runAction` is
 * what keeps the actions reachable while the form stays closed — Nova asks
 * canRun, then runAction, then update.
 *
 * `delete` is true, and deliberately so where ReportPolicy's is false: a
 * report is evidence a moderation decision hangs off, a ticket is a
 * conversation that is finished with once it is closed. Nothing references
 * the row, so Nova's ordinary delete is the right tool for clearing old
 * tickets — but deleting one also removes it from the member's own history on
 * `tickets.index`, so Closed is the ordinary end of a ticket and delete is for
 * spam.
 */
class TicketPolicy
{
    /**
     * Determine whether the admin can list tickets.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can read a ticket.
     */
    public function view(Admin $admin, TicketResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can author a ticket.
     *
     * No: tickets come from visitors, through the support form.
     */
    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can edit a ticket.
     *
     * No: the sender's own words are the ticket. Replying and closing are the
     * two actions.
     */
    public function update(Admin $admin, TicketResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin may run a non-destructive action.
     *
     * True, and load-bearing: without it both actions would fall through to
     * `update` above and be refused, and no ticket could be answered.
     */
    public function runAction(Admin $admin, TicketResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can delete a ticket.
     */
    public function delete(Admin $admin, TicketResource $resource): bool
    {
        return true;
    }
}
