<?php

namespace App\Pipelines\Messages\StartDirectConversation;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use LogicException;

/**
 * Passable for the open-a-direct-conversation flow.
 *
 * It deliberately does **not** extend MessageContentContext even though it
 * carries an optional first message. The text on this context is an argument
 * that gets handed to App\Actions\Messaging\SendMessage, which runs the send
 * flow — cleaning, persistence and notification included — so this flow never
 * writes message text itself and must not advertise, by inheriting that base,
 * that Shared\CleanContent may run against it. One cleaning path, reached one
 * way.
 *
 * `wasExisting` is what makes the flow idempotent. Two people can press "Message"
 * on each other's profile at the same moment, and `conversations` carries no
 * unique index that could stop a second direct thread between the same pair
 * from being created — the pair lives in `conversation_user`, and a unique
 * index there is per (conversation, user), not per set of participants. The
 * flow therefore looks the thread up first and, when it finds one, skips
 * creating and attaching rather than aborting: pressing "Message" twice reopens
 * the same conversation, which is what the button promises.
 *
 * @property-read User $initiator The user who pressed the button.
 * @property-read User $recipient The other side of the direct conversation.
 */
class StartDirectConversationContext
{
    /**
     * The conversation the flow found or created.
     */
    protected ?Conversation $conversation = null;

    /**
     * Whether the conversation was already there before this flow ran.
     */
    protected bool $wasExisting = false;

    /**
     * The opening message, once SendInitialMessage has written one. Null when
     * the conversation was opened with no message attached.
     */
    protected ?Message $initialMessage = null;

    public function __construct(
        public readonly User $initiator,
        public readonly User $recipient,
        public readonly ?string $initialMessageContent = null,
    ) {}

    /**
     * Whether the initiator asked for an opening message to be sent.
     */
    public function hasInitialMessage(): bool
    {
        return filled($this->initialMessageContent);
    }

    /**
     * Record a conversation that already existed between these two users.
     */
    public function setExistingConversation(Conversation $conversation): void
    {
        $this->conversation = $conversation;
        $this->wasExisting = true;
    }

    /**
     * Record the conversation this flow has just created.
     */
    public function setCreatedConversation(Conversation $conversation): void
    {
        $this->conversation = $conversation;
        $this->wasExisting = false;
    }

    public function wasExisting(): bool
    {
        return $this->wasExisting;
    }

    /**
     * Whether a conversation has been found or created yet.
     */
    public function hasConversation(): bool
    {
        return $this->conversation !== null;
    }

    /**
     * @throws LogicException When read before FindExistingDirectConversation and
     *                        CreateConversationRecord have run.
     */
    public function conversation(): Conversation
    {
        if ($this->conversation === null) {
            throw new LogicException(self::class.' has no conversation yet; CreateConversationRecord must run first.');
        }

        return $this->conversation;
    }

    public function setInitialMessage(Message $message): void
    {
        $this->initialMessage = $message;
    }

    /**
     * The opening message, or null when none was requested.
     */
    public function initialMessage(): ?Message
    {
        return $this->initialMessage;
    }
}
