<?php

namespace App\Pipelines\Messages\StartDirectConversation;

use Closure;

/**
 * Put both users in the new conversation.
 *
 * Skipped when the conversation was already there — `conversation_user` is
 * unique on (conversation_id, user_id), so re-attaching would raise an
 * integrity error rather than be a harmless no-op, and the participants of an
 * existing thread are by definition already correct.
 *
 * `attach()` rather than `sync()`: sync would detach anyone not named in the
 * array, which is the wrong verb for a thread that may one day have more than
 * two members.
 *
 * `last_read_at` is left null for both sides, meaning "has read nothing".
 * MarkReadForInitiator sets the initiator's cursor at the end of the flow;
 * leaving the recipient's null is what makes `Conversation::isUnreadFor()`
 * report the first message as unread.
 *
 * The relation is reloaded afterwards so the conversation the flow returns
 * carries its participants and their avatar media, which the redirect target
 * renders — otherwise Model::preventLazyLoading() fires on the first peer
 * lookup.
 */
class AttachParticipants
{
    public function handle(StartDirectConversationContext $context, Closure $next): mixed
    {
        if ($context->wasExisting()) {
            return $next($context);
        }

        $conversation = $context->conversation();

        $conversation->users()->attach([
            $context->initiator->getKey(),
            $context->recipient->getKey(),
        ]);

        $conversation->load('users.media');

        return $next($context);
    }
}
