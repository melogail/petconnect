<?php

namespace App\Pipelines\Messages\StartDirectConversation;

use App\Exceptions\Messaging\CannotMessageSelf;
use Closure;

/**
 * Refuse to open a direct conversation between a user and themselves.
 *
 * First in the flow because every later step is a question about two distinct
 * people: `Conversation::betweenParticipants` matches a direct thread with
 * exactly two participant rows, and `conversation_user` is unique on
 * (conversation_id, user_id), so a self-conversation would attach once, come
 * back with a null `otherParticipant()`, and hand every payload in the vertical
 * a peer that is not there.
 *
 * StoreConversationRequest rejects the same shape with `Rule::notIn` on
 * `recipient_id`, so an HTTP caller is stopped at the validator with a field
 * error. This step is
 * the invariant for the callers that pass no Form Request.
 *
 * @throws CannotMessageSelf
 */
class EnsureDistinctParticipants
{
    public function handle(StartDirectConversationContext $context, Closure $next): mixed
    {
        if ($context->initiator->is($context->recipient)) {
            throw CannotMessageSelf::make();
        }

        return $next($context);
    }
}
