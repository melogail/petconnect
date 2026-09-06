<?php

namespace App\Actions\Messaging;

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Pipelines\Messages\Send\EnsureParticipant;
use App\Pipelines\Messages\Send\EnsureRecipientAccepts;
use App\Pipelines\Messages\Send\NotifyRecipient;
use App\Pipelines\Messages\Send\PersistMessage;
use App\Pipelines\Messages\Send\SendMessageContext;
use App\Pipelines\Messages\Shared\CleanContent;
use Illuminate\Pipeline\Pipeline;

/**
 * Send a message into a conversation.
 *
 * A sequence — confirm the sender belongs here, confirm the other side is
 * accepting, clean the text, write the row, notify — so it runs as a pipeline
 * over a typed context rather than as one long method.
 *
 * Order is load bearing three times. Participation is settled before anything
 * is written, so a rejected send leaves nothing behind. Consent is asked
 * immediately after it, off the `users` collection EnsureParticipant loaded, so
 * it costs no query. Notification runs last, so nobody is told about a row that
 * failed to write.
 *
 * Send\EnsureRecipientAccepts is here rather than only in the
 * start-a-conversation flow because this is the path *every* message takes: a
 * consent rule enforced only when a thread is opened stops new threads and
 * nothing else, which is not what deactivating an account — or, later, blocking
 * somebody — means to a user already in a thread with them.
 *
 * Two callers, which is why this is an Action and not work inlined in the
 * controller: MessageController::store, and
 * Pipelines\Messages\StartDirectConversation\SendInitialMessage, which runs it
 * inside the transaction that creates the conversation. An opening message is
 * therefore cleaned, counted and notified exactly like every later one — the
 * legacy ConversationService wrote its own opening row and skipped all three.
 *
 * This Action is where the flow's tunables are resolved — the masked word list
 * and the mask itself — so no step reads config().
 *
 * There is no TouchConversationLastMessageAt step: `conversations.last_message_at`
 * belongs to App\Observers\MessageObserver, which maintains it on created,
 * deleted, restored and forceDeleted. A second writer in this flow would keep
 * the column correct only on the one path it covers and would have to be kept
 * in step with the observer forever. Send\PersistMessage associates the
 * conversation onto the message so the observer updates the very instance this
 * flow is holding, rather than re-fetching a second copy.
 *
 * Throttling is deliberately not a step here: it is a named limiter on the
 * route (`throttle:messages`, defined in AppServiceProvider).
 */
class SendMessage
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(
        Conversation $conversation,
        User $sender,
        string $content,
        ?MessageType $type = null,
    ): Message {
        /** @var list<string> $bannedWords */
        $bannedWords = config('bad-words.words', []);

        $context = new SendMessageContext(
            conversation: $conversation,
            sender: $sender,
            content: $content,
            type: $type ?? MessageType::Text,
            bannedWords: $bannedWords,
            mask: (string) config('bad-words.mask', '****'),
        );

        return $this->pipeline
            ->send($context)
            ->through([
                EnsureParticipant::class,
                EnsureRecipientAccepts::class,
                CleanContent::class,
                PersistMessage::class,
                NotifyRecipient::class,
            ])
            ->then(fn (SendMessageContext $completed): Message => $completed->message());
    }
}
