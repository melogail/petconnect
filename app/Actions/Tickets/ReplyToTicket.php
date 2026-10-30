<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Add a message to a ticket's thread and move the ticket to whoever's turn it
 * now is: a reply from the team marks it `Answered`, a reply from the sender
 * marks it `Open` again. Both halves — Settings\TicketController::reply and
 * Nova's Actions\ReplyToTicket — go through here, so there is one writer of
 * the status transition.
 *
 * A reply from the team also tells the sender through TicketRepliedNotification's
 * database and mail channels — or by mail alone, to the address on the row,
 * when the account has since been deleted. A reply from the sender notifies
 * nobody — the back office reads its queue off the `Open` badge in Nova's menu.
 *
 * An admin may answer a closed ticket, which reopens it as `Answered`; a
 * member may not — TicketPolicy::reply refuses before this runs.
 */
class ReplyToTicket
{
    public function handle(Ticket $ticket, User|Admin $author, string $body): TicketMessage
    {
        $message = DB::transaction(function () use ($ticket, $author, $body): TicketMessage {
            $message                  = $ticket->messages()->create([
                'author_type' => $author->getMorphClass(),
                'author_id'   => $author->getKey(),
                'body'        => $body,
            ]);

            $ticket->status           = $author instanceof Admin ? TicketStatus::Answered : TicketStatus::Open;
            $ticket->closed_at        = null;
            $ticket->last_activity_at = now();
            $ticket->save();

            return $message;
        });

        if ($author instanceof Admin) {
            $recipient = $ticket->user ?? Notification::route('mail', $ticket->email);
            $recipient->notify(new TicketRepliedNotification($ticket, $message));
        }

        return $message;
    }
}
