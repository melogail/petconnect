<?php

namespace App\Pipelines\Messages\Send;

use App\Exceptions\Messaging\NotAConversationParticipant;
use App\Models\User;
use Closure;

/**
 * Refuse to write a message into a conversation the sender is not part of.
 *
 * The domain invariant behind the HTTP check, not a replacement for it:
 * MessagePolicy::create decides this at the call site in MessageController,
 * per .ai/rules/controllers.md, so a non-participant gets a 403 and never
 * reaches the pipeline. This step is what holds for the callers that pass no
 * policy — the start-conversation flow, a seeder, a console command — and what
 * keeps the invariant true if a future route forgets to authorize.
 *
 * It also does the loading that the rest of the flow needs. `users` is read
 * from the conversation once, here, and NotifyRecipient reads the same loaded
 * collection to work out who to tell, so participation costs the flow one query
 * in total rather than one per step. Checking with `hasParticipant()` would
 * have issued an `exists` query and left NotifyRecipient to issue its own.
 *
 * @throws NotAConversationParticipant
 */
class EnsureParticipant
{
    public function handle(SendMessageContext $context, Closure $next): mixed
    {
        $conversation = $context->conversation;

        $conversation->loadMissing('users');

        $isParticipant = $conversation->users->contains(
            fn (User $participant): bool => $participant->is($context->sender)
        );

        if (! $isParticipant) {
            throw NotAConversationParticipant::for($context->sender, $conversation);
        }

        return $next($context);
    }
}
