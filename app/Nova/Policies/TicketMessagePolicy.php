<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\TicketMessage as TicketMessageResource;

/**
 * Authorization for the TicketMessage resource, on the `admin` guard.
 *
 * Read only in every direction. A message is what somebody wrote, so it is
 * neither edited nor deleted on its own — a ticket is cleared whole, and its
 * messages cascade. The team's own replies are not created here either: they
 * go through Actions\ReplyToTicket on the Ticket resource, which is what
 * moves the ticket to Answered and notifies the sender. A message typed
 * straight into this resource would do neither, so `create` is false.
 */
class TicketMessagePolicy
{
    /**
     * Determine whether the admin can list messages.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can read a message.
     */
    public function view(Admin $admin, TicketMessageResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can write a message directly.
     *
     * No: replies go through the Ticket resource's ReplyToTicket action.
     */
    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can edit a message.
     */
    public function update(Admin $admin, TicketMessageResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can delete a message.
     */
    public function delete(Admin $admin, TicketMessageResource $resource): bool
    {
        return false;
    }
}
