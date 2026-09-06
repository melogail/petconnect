<?php

namespace App\Pipelines\Messages\StartDirectConversation;

use App\Actions\Messaging\SendMessage;
use Closure;

/**
 * Send the opening message, when the initiator wrote one.
 *
 * It delegates to App\Actions\Messaging\SendMessage — the same Action
 * MessageController::store runs — rather than writing a row itself. That is the
 * "second caller" .ai/rules/pipelines.md asks for before an Action is extracted
 * at all, and it is the only way the opening message is guaranteed to be
 * cleaned, to move `conversations.last_message_at` and to notify the recipient
 * exactly as every later message does. The legacy ConversationService did write
 * its own row here, through the repository, which is precisely why the opening
 * message was the one message that skipped whatever the send path did.
 *
 * It runs **inside the transaction the Action opened**, which is the reason
 * this is a step in this flow rather than a second call from the controller: a
 * conversation created with an opening message that then fails to write would
 * leave an empty thread in somebody's inbox, and a caller that had to remember
 * to wrap both calls would eventually forget.
 *
 * Sending is conditional; everything else in the flow is not. A conversation
 * opened with no message is a real state — the profile button offers both — and
 * `Conversation::isUnreadFor()` reports false for a thread with no last
 * message, so an empty thread is simply quiet rather than broken.
 */
class SendInitialMessage
{
    public function __construct(private readonly SendMessage $sendMessage) {}

    public function handle(StartDirectConversationContext $context, Closure $next): mixed
    {
        if (! $context->hasInitialMessage()) {
            return $next($context);
        }

        $context->setInitialMessage($this->sendMessage->handle(
            conversation: $context->conversation(),
            sender: $context->initiator,
            content: (string) $context->initialMessageContent,
        ));

        return $next($context);
    }
}
