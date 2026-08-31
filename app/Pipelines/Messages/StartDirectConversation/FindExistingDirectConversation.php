<?php

namespace App\Pipelines\Messages\StartDirectConversation;

use App\Models\Conversation;
use Closure;

/**
 * Look for the direct conversation these two already share.
 *
 * `Conversation::betweenParticipants` is the model scope that answers it —
 * direct, both users present, exactly two participants — so the flow builds no
 * query of its own and the definition of "the same conversation" lives in one
 * place next to `direct()` and `forParticipant()`.
 *
 * Finding one is not an abort. Pressing "Message" on a profile has to reopen
 * the existing thread rather than fail or fork it, so this step records what it
 * found and CreateConversationRecord and AttachParticipants stand down. That is
 * what makes the whole flow idempotent, which matters because `conversations`
 * carries no unique index over a participant pair — the pair lives in
 * `conversation_user`, whose unique index is per (conversation, user) and
 * cannot express "these two, once".
 *
 * The `users` relation is loaded with the result, because everything after this
 * step wants it: SendInitialMessage's participation check reads it, and the
 * controller redirects to a page that renders the peer.
 */
class FindExistingDirectConversation
{
    public function handle(StartDirectConversationContext $context, Closure $next): mixed
    {
        $existing = Conversation::query()
            ->betweenParticipants($context->initiator, $context->recipient)
            ->with('users.media')
            ->first();

        if ($existing !== null) {
            $context->setExistingConversation($existing);
        }

        return $next($context);
    }
}
