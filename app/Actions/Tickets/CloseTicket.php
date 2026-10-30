<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Close a ticket. Either side may: the sender from their ticket page when the
 * answer did the job, the team from Nova's ChangeTicketStatus. When the team
 * closes it the sender is told, since nothing else would; when the sender
 * closes their own, nobody needs telling.
 *
 * Idempotent on purpose — closing a closed ticket changes nothing and sends
 * nothing — so a double-click or a repeated Nova selection is harmless.
 */
class CloseTicket
{
    public function handle(Ticket $ticket, User|Admin $actor): Ticket
    {
        if ($ticket->isClosed()) {
            return $ticket;
        }

        $ticket->status           = TicketStatus::Closed;
        $ticket->closed_at        = now();
        $ticket->last_activity_at = now();
        $ticket->save();

        if ($actor instanceof Admin) {
            $recipient = $ticket->user ?? Notification::route('mail', $ticket->email);
            $recipient->notify(new TicketRepliedNotification($ticket, null));
        }

        return $ticket;
    }
}
