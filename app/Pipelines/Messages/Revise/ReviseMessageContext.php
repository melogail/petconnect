<?php

namespace App\Pipelines\Messages\Revise;

use App\Models\Message;
use App\Pipelines\Messages\MessageContentContext;

/**
 * Passable for the edit-a-message flow.
 *
 * The flow directory is `Revise` rather than `UpdateMessage` because
 * App\Actions\Messaging\UpdateMessage is the class that runs it, and
 * .ai/rules/pipelines.md forbids a flow namespace that reads identically to an
 * Action class name — the same reason Comments has `ReviseComment`.
 *
 * Only the text is editable: the conversation, the sender and the type are
 * settled when the message is sent and no edit reopens them. There is therefore
 * no attribute bag here and no key an omission can silently wipe.
 *
 * Whether an edit is still allowed at all is not asked here. That is a window
 * in wall-clock time (`petconnect.messaging.edit_window_minutes`) and it is
 * MessagePolicy::update's decision, made at the call site in
 * MessageController — one place to audit, per .ai/rules/controllers.md.
 *
 * The message is reached through `message()` rather than as a public property,
 * so that every context in this domain hands its message back the same way:
 * Send\SendMessageContext has to use an accessor because its message does not
 * exist until PersistMessage has run, and one access style across the two flows
 * is worth more than advertising, through the shape of the member, which flow
 * produced the model.
 */
class ReviseMessageContext extends MessageContentContext
{
    /**
     * @param  list<string>  $bannedWords
     */
    public function __construct(
        protected readonly Message $message,
        string $content,
        array $bannedWords = [],
        string $mask = '****',
    ) {
        parent::__construct($content, $bannedWords, $mask);
    }

    /**
     * The message being revised. Always present: it is a constructor argument,
     * so unlike Send\SendMessageContext::message() this can never throw.
     */
    public function message(): Message
    {
        return $this->message;
    }
}
