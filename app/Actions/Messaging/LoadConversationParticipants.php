<?php

namespace App\Actions\Messaging;

use App\Models\Conversation;

/**
 * Load everything the thread page's conversation header renders.
 *
 * That is the participants and their avatar media, and nothing else: the
 * messages themselves are PaginateConversationMessages', and `lastMessage` is
 * an inbox concern — on a page that is showing the whole thread, the last
 * message is already on it.
 *
 * It exists because a route-bound conversation arrives with no relations and
 * ConversationResource walks two of them, and because a controller must not
 * compose the query itself. `users.media` is the one that is easy to miss: the
 * peer avatar is a `getFirstMediaUrl()` call, and `preventLazyLoading` does not
 * arm on a single-model result set (see .ai/rules/app.md), so forgetting it
 * would be silent on exactly this page.
 *
 * `loadMissing` rather than `load`, so a caller that already has the relations
 * — the start-conversation flow hands its conversation straight to a redirect —
 * pays nothing.
 */
class LoadConversationParticipants
{
    public function handle(Conversation $conversation): Conversation
    {
        return $conversation->loadMissing('users.media');
    }
}
