<?php

namespace App\Http\Resources\Ticket;

use App\Models\Admin;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One message of a ticket's thread, for `settings/Ticket.vue`.
 *
 * `is_from_support` decides which side the bubble sits on and is read off the
 * morph alias, no author needed. `author_name` is the member's or the admin's
 * name for the byline — the controller eager loads `messages.author` (one
 * query per author type, not per message) — and null for an author whose
 * account is gone, where the client falls back to its own localized label.
 *
 * @mixin TicketMessage
 */
class TicketMessageResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     body: string,
     *     is_from_support: bool,
     *     author_name: string|null,
     *     created_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $author = $this->author;

        return [
            'id'              => $this->id,
            'body'            => $this->body,
            'is_from_support' => $this->isFromSupport(),
            'author_name'     => $author instanceof User || $author instanceof Admin ? $author->name : null,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
