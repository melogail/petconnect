<?php

namespace App\Pipelines\Messages\StartDirectConversation;

use Closure;

/**
 * Move the initiator's read cursor to now.
 *
 * Last in the flow, and after SendInitialMessage on purpose: the initiator is
 * about to be redirected onto the thread they just opened, so it must not
 * appear unread to them the moment it appears in their inbox. Their own opening
 * message would not have made it unread — `Conversation::isUnreadFor()` already
 * returns false when the last message is the reader's own — but an existing
 * thread that this flow merely reopened may hold messages from the other side
 * that the initiator is now looking at.
 *
 * Per-user read state is `conversation_user.last_read_at`; there is no
 * `messages.read_at` column and no per-message receipt. `markAsReadFor()` is
 * the model method that owns the write, and it no-ops for a non-participant.
 *
 * This is the only place in the vertical where a cursor moves as a side effect
 * of something else, and it is a POST. Reading a conversation does **not** move
 * it — see ConversationController::show.
 */
class MarkReadForInitiator
{
    public function handle(StartDirectConversationContext $context, Closure $next): mixed
    {
        $context->conversation()->markAsReadFor($context->initiator);

        return $next($context);
    }
}
