<?php

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of one conversation's messages, newest first.
 *
 * ## Newest first, deliberately, unlike the legacy repository
 *
 * The legacy MessageRepository paged with `oldest()`, so page one of a
 * three-year-old thread was the first fifty messages ever sent in it and the
 * current conversation was on the last page. Chat reads from the end: page one
 * here is the end of the thread, and older pages are walked backwards from it.
 * The client reverses a page for display; the index on
 * (conversation_id, created_at) serves either direction.
 *
 * `id` descending is a tiebreak, not decoration. Several messages can share a
 * `created_at` to the second — a seeder, a test, or two quick taps — and a sort
 * with ties is not a stable paginator: the same row can appear on two pages or
 * on none. The primary key is monotonic and settles it.
 *
 * `sender.media` is eager loaded because MessageResource emits the sender
 * through UserSummaryResource, which reads an avatar with `getFirstMediaUrl()`.
 * Without it that is one query per message on the page.
 *
 * ## The conversation is chaperoned back onto every message
 *
 * MessageResource emits `can_pin`, which asks MessagePolicy::pin, which asks
 * `$message->conversation->hasParticipant($viewer)`. Read naively that is a
 * relation walk per rendered message — thirty on a page, and thirty more for
 * the membership check behind each.
 *
 * `chaperone('conversation')` sets the inverse relation to the parent this
 * Action was handed, so every message on the page carries the conversation the
 * page is already showing. It runs in PHP after the query the paginator was
 * issuing anyway and adds no round trip. `loadMissing('users')` above it is
 * what makes the membership check free too — one query, once, not one per
 * message, and free on `conversations.show`, where
 * Actions\Messaging\LoadConversationParticipants has already loaded
 * `users.media` onto the same instance. It is a genuine extra query only on
 * `conversations.messages.index`, whose conversation arrives straight from a
 * route binding with nothing loaded.
 *
 * The conversation's visibility is not re-derived here. `{conversation}` is
 * route-bound and soft-deleted rows do not bind, and a `{message}` route is
 * covered by Message::resolveRouteBinding(). A caller handing this Action a
 * conversation from somewhere other than a route binding owns that check.
 */
class PaginateConversationMessages
{
    /**
     * @return LengthAwarePaginator<int, Message>
     */
    public function handle(Conversation $conversation, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= (int) config('petconnect.messaging.thread_per_page', 30);

        $conversation->loadMissing('users');

        return $conversation->messages()
            ->chaperone('conversation')
            ->with('sender.media')
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);
    }
}
