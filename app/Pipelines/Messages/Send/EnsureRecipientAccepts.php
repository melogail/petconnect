<?php

namespace App\Pipelines\Messages\Send;

use App\Exceptions\Messaging\RecipientNotAcceptingMessages;
use Closure;

/**
 * Refuse to write a message the other side is not accepting.
 *
 * The recipient-side consent seam for **every** message, which is what makes it
 * mean anything. It used to live only in the start-a-conversation flow, so a
 * rule enforced there — `is_active` today, a block list tomorrow — stopped a
 * new thread and nothing else: a user who deactivated kept receiving messages
 * in every thread they already had, and blocking somebody you were already
 * talking to would have changed nothing at all. `messages.store` posts into an
 * existing conversation and never traverses that flow. This step is on the path
 * both writes take: MessageController::store through
 * App\Actions\Messaging\SendMessage, and the opening message through
 * StartDirectConversation\SendInitialMessage, which runs the same Action.
 *
 * It runs after EnsureParticipant and before CleanContent and PersistMessage:
 * after, because EnsureParticipant is what loads `users`, so this step costs no
 * query at all; before, because a refused message must leave no row behind and
 * must not notify anybody.
 *
 * The rule is not written here. Conversation::acceptsMessagesFrom() decides who
 * to ask and User::acceptsMessagesFrom() is the rule itself, so the same
 * question is asked identically by MessagePolicy::create at the HTTP seam, by
 * StartDirectConversation\EnsureRecipientAccepts for a thread opened with no
 * message, and by this step. Extending consent means editing one method, not
 * three.
 *
 * As with EnsureParticipant, this is the domain invariant behind the HTTP
 * check, not a replacement for it: MessagePolicy::create decides it at the call
 * site per .ai/rules/controllers.md and answers 403, so reaching this exception
 * means the authorization in front of the pipeline was bypassed or a caller
 * passed no policy.
 *
 * @throws RecipientNotAcceptingMessages
 */
class EnsureRecipientAccepts
{
    public function handle(SendMessageContext $context, Closure $next): mixed
    {
        if (! $context->conversation->acceptsMessagesFrom($context->sender)) {
            throw RecipientNotAcceptingMessages::for($context->sender, $context->conversation);
        }

        return $next($context);
    }
}
