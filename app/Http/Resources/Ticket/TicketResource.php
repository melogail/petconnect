<?php

namespace App\Http\Resources\Ticket;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ticket's header, as the member's pages read it: `settings/Tickets.vue`
 * lists these, `settings/Ticket.vue` shows one above its thread.
 *
 * Served to the ticket's own sender only (TicketPolicy), so it may name the
 * subject in full. `name` and `email` are left off: the reader is the sender.
 * The thread is not here — `TicketMessageResource` is the thread, shipped as
 * its own prop so a list of headers never carries one.
 *
 * `messages_count` reads the `withCount('messages')` alias with `??` so a
 * caller that did not count ships 0 rather than lazy loading; only the index
 * counts (ListUserTickets), the show page has the thread itself.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     category: string,
     *     subject: string,
     *     status: string,
     *     messages_count: int,
     *     last_activity_at: string|null,
     *     closed_at: string|null,
     *     created_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'category'         => $this->category->value,
            'subject'          => $this->subject,
            'status'           => $this->status->value,
            'messages_count'   => (int) ($this->messages_count ?? 0),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'closed_at'        => $this->closed_at?->toIso8601String(),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
