<?php

namespace App\Pipelines\Messages\StartDirectConversation;

use App\Enums\ConversationType;
use App\Models\Conversation;
use Closure;

/**
 * Create the conversation row, unless FindExistingDirectConversation already
 * found one.
 *
 * The short circuit skips this step's own work and passes the context on — it
 * is not an abort, and the steps after it still run: an existing conversation
 * can still take an opening message and still advances the initiator's read
 * cursor. A step never knows which step ran before it, so the question it asks
 * is about the context ("is there a conversation yet?"), not about its
 * neighbour.
 *
 * `type` is written from the ConversationType case rather than a string, and
 * `last_message_at` is left alone: App\Observers\MessageObserver owns that
 * column and will set it when the first message lands.
 */
class CreateConversationRecord
{
    public function handle(StartDirectConversationContext $context, Closure $next): mixed
    {
        if ($context->hasConversation()) {
            return $next($context);
        }

        $context->setCreatedConversation(Conversation::create([
            'type' => ConversationType::Direct,
        ]));

        return $next($context);
    }
}
