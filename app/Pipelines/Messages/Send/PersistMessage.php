<?php

namespace App\Pipelines\Messages\Send;

use App\Models\Message;
use Closure;

/**
 * Write the message row.
 *
 * No transaction: this is one INSERT, which is already atomic. The house rule
 * is the one .ai/rules/pipelines.md sets — a transaction is opened by the
 * Action when a flow writes several rows that must land together, never by a
 * step around a single statement. The start-conversation flow does open one,
 * and this step runs inside it when a conversation is opened with a first
 * message.
 *
 * The sender and the conversation are stamped from the context, not from the
 * submitted payload. `conversation_id` and `sender_id` are both in Message's
 * #[Fillable] because factories fill them, so forwarding a validated request
 * bag straight into create() would let a caller post a message into somebody
 * else's thread under somebody else's name. Nothing that came off the wire as a
 * value reaches this insert except `content`, and `type` arrives as a
 * MessageType case rather than a string.
 *
 * `status` is deliberately absent: it is outside #[Fillable] because delivery
 * state is the application's to own, and Message's `$attributes` default puts
 * `sent` on the instance as well as in the row, so the model returned here
 * carries a real MessageStatus without a refresh() (see .ai/rules/models.md).
 * `pinned_by` and `pinned_at` are absent for the same reason — pinning is
 * App\Actions\Messaging\TogglePinMessage's to set.
 *
 * The conversation is `associate()`d rather than left to the foreign key alone.
 * App\Observers\MessageObserver::created() reads `$message->conversation` to
 * write `conversations.last_message_at`, and without the relation in memory it
 * would re-SELECT a row this flow is already holding — and would update a
 * second instance, leaving the context's conversation stale. Associating costs
 * nothing and gives the observer, and every caller after it, the same object.
 * `last_message_at` itself is never set here: the observer owns that column on
 * create, delete, restore and force delete, and a second writer would be a
 * second thing to keep correct.
 *
 * The sender is set onto the message with its avatar media, because a caller
 * may serialise the return value straight back and Model::preventLazyLoading()
 * would otherwise fire the moment UserSummaryResource asked for it. It is
 * `setRelation()` over the User the context already holds rather than
 * `load('sender.media')`, which would re-SELECT that user and their media —
 * two queries for a model in hand, the anti-pattern the legacy repositories'
 * id-taking signatures institutionalised. `loadMissing('media')` on the sender
 * is the one query that may remain, and only when the caller had not loaded it.
 */
class PersistMessage
{
    public function handle(SendMessageContext $context, Closure $next): mixed
    {
        $message = new Message([
            'sender_id' => $context->sender->getKey(),
            'content' => $context->content(),
            'type' => $context->type,
        ]);

        $message->conversation()->associate($context->conversation);
        $message->save();

        $message->setRelation('sender', $context->sender->loadMissing('media'));

        $context->setMessage($message);

        return $next($context);
    }
}
