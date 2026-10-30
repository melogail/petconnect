<?php

namespace App\Actions\Tickets;

use App\Enums\TicketCategory;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Open a support ticket for a member: the header row plus its first message,
 * in one transaction.
 *
 * The sender's identity comes off the account, never off the request. The row
 * still carries `name` and `email` so the back office reads every ticket the
 * same way, and so a ticket outlives a later change of address or a deleted
 * account (`user_id` is nulled on delete; the address on the row is then
 * where the team's replies go).
 *
 * Tickets are members-only by design — a ticket has a thread and a page to
 * follow it on, neither of which a guest has. A guest's message is
 * Actions\Contact\SubmitContactMessage.
 */
class OpenTicket
{
    public function handle(User $sender, TicketCategory $category, string $subject, string $message): Ticket
    {
        return DB::transaction(function () use ($sender, $category, $subject, $message): Ticket {
            $ticket                   = new Ticket([
                'user_id'  => $sender->getKey(),
                'name'     => $sender->name,
                'email'    => $sender->email,
                'category' => $category,
                'subject'  => $subject,
            ]);
            $ticket->last_activity_at = now();
            $ticket->save();

            $ticket->messages()->create([
                'author_type' => $sender->getMorphClass(),
                'author_id'   => $sender->getKey(),
                'body'        => $message,
            ]);

            return $ticket;
        });
    }
}
