<?php

namespace App\Exceptions\Messaging;

use App\Models\Conversation;
use App\Models\User;
use RuntimeException;

/**
 * A message was about to be written into a thread whose other side is not
 * accepting messages from the sender.
 *
 * The recipient-side consent invariant for the send flow — the thing the
 * conversation-creation seam alone could not hold, because `messages.store`
 * writes into a thread that already exists and never runs it. Today the rule is
 * `users.is_active` (a deactivated account receives nothing) and it is stated
 * once, in User::acceptsMessagesFrom(); a block list lands there and both write
 * paths inherit it.
 *
 * Unreachable over HTTP, the same way NotAConversationParticipant is:
 * MessagePolicy::create asks Conversation::acceptsMessagesFrom() at the call
 * site in MessageController, so a refused send is a 403 long before the
 * pipeline runs, and the start-conversation flow refuses at
 * StartDirectConversation\EnsureRecipientAccepts with a field error on
 * `recipient_id`. This holds for the callers that pass no policy — a seeder, a
 * console command, another Action — and for the day a new route forgets to
 * authorize.
 *
 * A plain RuntimeException rather than a ValidationException, because the abort
 * is not attributable to a submitted field: the conversation came from the URL
 * and there is nothing the sender could retype to fix it.
 * .ai/rules/pipelines.md reserves the ValidationException base for field-level
 * problems, which is why the same rule aborting the *start* flow — where
 * `recipient_id` is a control the client picked — throws ConversationNotPermitted
 * instead.
 *
 * The message names the two rows, not the reason. Which of "deactivated",
 * "blocked you" or "does not accept messages from strangers" it was is a fact
 * about another person, and the HTTP layer never gets to tell them apart — see
 * ConversationNotPermitted.
 */
class RecipientNotAcceptingMessages extends RuntimeException
{
    public static function for(User $sender, Conversation $conversation): self
    {
        return new self(sprintf(
            'Conversation [%d] is not accepting messages from user [%d].',
            $conversation->getKey(),
            $sender->getKey(),
        ));
    }
}
