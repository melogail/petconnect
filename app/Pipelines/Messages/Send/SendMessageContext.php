<?php

namespace App\Pipelines\Messages\Send;

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Pipelines\Messages\MessageContentContext;
use LogicException;

/**
 * Passable for the send-a-message flow.
 *
 * The conversation arrives as a resolved model rather than as an id: every
 * caller already holds one — the controller from route model binding, the
 * start-conversation flow from the row it just created — and re-fetching it
 * from an id inside the pipeline would be a second SELECT for a model in hand,
 * which is the anti-pattern the legacy repositories' `update(int $id)`
 * signatures institutionalised.
 *
 * `type` is a MessageType case, never a raw string, so nothing that came off
 * the wire as a value reaches the insert except `content`.
 *
 * The flow directory is `Send` rather than `SendMessage`:
 * App\Actions\Messaging\SendMessage is the class that runs it, and
 * .ai/rules/pipelines.md forbids a flow namespace that reads identically to an
 * Action class name.
 */
class SendMessageContext extends MessageContentContext
{
    /**
     * The written message, once PersistMessage has run.
     */
    protected ?Message $message = null;

    /**
     * @param  list<string>  $bannedWords
     */
    public function __construct(
        public readonly Conversation $conversation,
        public readonly User $sender,
        string $content,
        public readonly MessageType $type = MessageType::Text,
        array $bannedWords = [],
        string $mask = '****',
    ) {
        parent::__construct($content, $bannedWords, $mask);
    }

    public function setMessage(Message $message): void
    {
        $this->message = $message;
    }

    /**
     * @throws LogicException When read before PersistMessage has run.
     */
    public function message(): Message
    {
        if ($this->message === null) {
            throw new LogicException(self::class.' has no message yet; PersistMessage must run first.');
        }

        return $this->message;
    }
}
