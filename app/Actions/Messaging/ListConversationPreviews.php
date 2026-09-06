<?php

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The newest handful of a user's conversations, for the header's messages menu.
 *
 * ## Why this is not BuildInbox with a smaller page size
 *
 * `conversations.previews` used to call BuildInbox with `perPage = 5`, and the
 * two loaders looked interchangeable because the payload was. They are not,
 * once the payload stops being: the menu renders a peer, a snippet and a
 * timestamp, so it needs `users.media` (the avatar) and `lastMessage` (the
 * snippet, its sender and its time) and nothing else.
 *
 * BuildInbox additionally loads `lastMessage.sender.media`, because
 * Http\Resources\Message\MessageResource embeds the sender as a
 * UserSummaryResource with an avatar, and chaperones the conversation back onto
 * the last message so MessagePolicy::pin can be asked per row. The dropdown
 * asks neither question. Measured on the development MySQL database against
 * user 1, whose inbox holds 5 conversations: the endpoint was **8 queries**
 * through BuildInbox and is **5** through this Action (1 pivot select + 2 for
 * `users.media` + 1 for `lastMessage`, plus CountUnreadConversations' single
 * aggregate). Both are flat in the number of rows returned.
 *
 * No paginator, and that is the second half of the same decision. A dropdown
 * does not page, so a LengthAwarePaginator here bought a second COUNT query and
 * published `links` / `meta.links` that pointed at a `?page=2` nobody would
 * ever fetch — an invitation to build a "load more" against an endpoint whose
 * whole contract is "the newest five". The unread badge is not a property of
 * this list at all and never was: it rides on the response through
 * `additional()`, out of Actions\Messaging\CountUnreadConversations, and counts
 * the viewer's whole inbox.
 *
 * BuildInbox is deliberately left alone. It is the inbox page's loader, its
 * query count is pinned by the suite, and reshaping it to serve two payloads is
 * how one screen's tuning ends up deciding the other's.
 *
 * ## What must stay in step with BuildInbox
 *
 * The ordering, and only the ordering: `last_message_at` then `updated_at`,
 * descending, so the five rows in the menu are the first five rows of the
 * inbox. BuildInbox's docblock carries the reasoning and the EXPLAIN — no index
 * can serve that sort, because the filter is on `conversation_user` and both
 * sort columns are on `conversations` — and it applies here unchanged.
 *
 * The eager loads are **not** shared and must not be re-synchronised: they
 * differ on purpose, and each list is the one its own resource walks.
 *
 * @see App\Actions\Messaging\BuildInbox for the inbox page's loader.
 * @see App\Actions\Messaging\CountUnreadConversations for the badge beside it.
 */
class ListConversationPreviews
{
    /**
     * @return Collection<int, Conversation>
     */
    public function handle(User $user, ?int $limit = null): Collection
    {
        $limit ??= (int) config('petconnect.messaging.preview_per_page', 5);

        return $user->conversations()
            ->with(['users.media', 'lastMessage'])
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.updated_at')
            ->limit($limit)
            ->get();
    }
}
