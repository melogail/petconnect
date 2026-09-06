<?php

namespace App\Exceptions\Messaging;

use App\Models\Conversation;
use App\Models\User;
use RuntimeException;

/**
 * A message was about to be written into a conversation its sender is not part
 * of.
 *
 * Unreachable over HTTP: MessagePolicy::create decides participation at the
 * call site in MessageController, per .ai/rules/controllers.md, and a
 * non-participant is a 403 long before the pipeline runs. The step behind this
 * exception is the invariant for every other caller — the start-conversation
 * flow, a seeder, a console command — none of which pass a policy.
 *
 * It is a plain RuntimeException rather than a ValidationException, because the
 * abort is not attributable to a submitted field: the conversation came from
 * the URL, not from a form control, and there is nothing the sender could
 * retype to fix it. .ai/rules/pipelines.md reserves the ValidationException
 * base for field-level problems and says an invariant breach gets its own
 * class, which is this one. Reaching it means the authorization in front of the
 * pipeline was bypassed or removed, and a 500 is the honest signal for that.
 */
class NotAConversationParticipant extends RuntimeException
{
    public static function for(User $user, Conversation $conversation): self
    {
        return new self(sprintf(
            'User [%d] is not a participant of conversation [%d] and cannot write to it.',
            $user->getKey(),
            $conversation->getKey(),
        ));
    }
}
