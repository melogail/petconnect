<?php

namespace App\Pipelines\Messages\Send;

use App\Models\User;
use App\Notifications\NewMessageNotification;
use Closure;

/**
 * Tell the other side of the conversation that a message arrived.
 *
 * Runs last, after PersistMessage, so nobody is told about a row that failed to
 * write. Recipients come from the `users` collection EnsureParticipant already
 * loaded, so this step issues no query of its own to find them, and the sender
 * is filtered out — writing to yourself is not an event.
 *
 * It notifies every other participant rather than "the" recipient, though a
 * direct conversation has exactly one: the plural is free, and the day
 * ConversationType gains a `group` case this step is already right.
 *
 * The legacy app sent nothing at all here, so a user learned they had mail only
 * by opening the inbox. One notification per message is a deliberate choice
 * over collapsing them per unread run: it matches ModelCommentedNotification's
 * per-event shape, the row is what the bell menu reads, and the volume is
 * bounded by the `messages` rate limiter rather than by a rule inside the flow.
 * If that volume ever becomes the problem, collapsing belongs in the inbox
 * query, not here.
 *
 * Not queued, matching every other notification in the app — there is no queue
 * worker configured yet and the sync driver would only pretend.
 */
class NotifyRecipient
{
    public function handle(SendMessageContext $context, Closure $next): mixed
    {
        $message = $context->message();

        $context->conversation->users
            ->reject(fn (User $participant): bool => $participant->is($context->sender))
            ->each(fn (User $recipient) => $recipient->notify(new NewMessageNotification($message)));

        return $next($context);
    }
}
