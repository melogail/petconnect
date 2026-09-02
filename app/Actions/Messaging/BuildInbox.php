<?php

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A page of a user's conversations, most recently active first.
 *
 * Query composition for one unit of business work, so it is an Action rather
 * than a repository — see .ai/rules/app.md, where the legacy
 * ConversationRepository (injected straight into the controller, defeating its
 * own abstraction) is the recorded reason there is no repository layer here.
 *
 * ## Everything the inbox renders is eager loaded, avatars included
 *
 * The list is read through App\Http\Resources\Conversation\ConversationResource,
 * which walks four things per row: the participants, the peer's avatar, the
 * last message, and its sender. All four are loaded here, so the page costs the
 * same number of queries whether it holds one conversation or fifteen.
 *
 * Any query figure pinned for this Action — the suite pins 7 — is
 * Action-scoped and measured under phpunit.xml's `SESSION_DRIVER=array`; a
 * real request pays 2-3 more for the `sessions` and `cache` tables while
 * `.env` keeps the `database` drivers. See .ai/rules/app.md.
 *
 * `users.media` is the one that is easy to miss and expensive to miss. The peer
 * avatar is a `getFirstMediaUrl()` call, and the legacy inbox loaded
 * `['users', 'lastMessage.sender']` and then asked `$peer->getMedia('users')`
 * for every row — one query per conversation, silently, because medialibrary's
 * `force_lazy_loading` turned the access into a permitted `loadMissing()`. That
 * flag is off outside production now (see .ai/rules/config.md), so the same
 * mistake throws here instead of costing a page.
 *
 * `lastMessage.sender.media` is loaded for the same reason one level down:
 * MessageResource emits its sender through UserSummaryResource, which reads an
 * avatar too.
 *
 * ## The conversation is chaperoned back onto its own last message
 *
 * MessageResource emits `can_pin`, which asks MessagePolicy::pin, which reaches
 * `$message->conversation`. The last message of every row on this page belongs
 * to the row it is sitting in, so `chaperone('conversation')` hands each one
 * the conversation already loaded beside it rather than letting the resource
 * walk back to the database for a model the page is holding. It is pure PHP —
 * Eloquent sets the inverse relation while matching the eager load — so the
 * page stays at the same query count, and the conversation it receives is the
 * one whose `users` are loaded, which is what keeps the membership check free
 * as well.
 *
 * The closure is typed `HasOne`, not `Builder`: an eager-load constraint
 * closure is handed the Relation, and type hinting a Builder there is a runtime
 * TypeError nothing static in this project catches (.ai/rules/app.md).
 *
 * ## Unread is computed from loaded relations, not from a query per row
 *
 * `Conversation::isUnreadFor()` compares the last message against the reader's
 * `conversation_user.last_read_at`, and both are already in memory once `users`
 * and `lastMessage` are loaded — the pivot is cast to a Carbon by
 * App\Models\ConversationUser. There is no `messages.read_at` column; the
 * cursor is the whole read model.
 *
 * ## Ordering
 *
 * `last_message_at` descending, then `updated_at`, so a conversation that has
 * been opened but never written into still has a stable place in the list
 * instead of an arbitrary one — `last_message_at` is null until the first
 * message lands, and App\Observers\MessageObserver is what fills it.
 *
 * @see App\Actions\Messaging\PaginateConversationMessages for one thread's
 *      messages.
 */
class BuildInbox
{
    /**
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function handle(User $user, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= (int) config('petconnect.messaging.inbox_per_page', 15);

        return $user->conversations()
            ->with([
                'users.media',
                'lastMessage' => fn (HasOne $lastMessage): HasOne => $lastMessage->chaperone('conversation'),
                'lastMessage.sender.media',
            ])
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.updated_at')
            ->paginate($perPage);
    }
}
