<?php

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Pipelines\Messages\Revise\PersistContent;
use App\Pipelines\Messages\Revise\ReviseMessageContext;
use App\Pipelines\Messages\Shared\CleanContent;
use Illuminate\Pipeline\Pipeline;

/**
 * Apply an edit to a message.
 *
 * Two steps is a short pipeline, and .ai/rules/pipelines.md says to default to
 * inline work — but the first of those steps is Shared\CleanContent, and
 * running it here is the whole point: it is the same class the send flow runs,
 * so a message cannot be edited around the filter it was sent through. Writing
 * the sanitising inline would have made the send path and the edit path two
 * separate statements of the same rule, which is how the legacy pair drifted.
 * It is also what makes `Shared/` the honest directory for that step: two flows
 * use it. The Comments domain reached the same shape for the same reason
 * (ReviseComment).
 *
 * Only `content` is writable, plus the `edited_at` stamp Revise\PersistContent
 * sets alongside it. The conversation, the sender and the type are settled when
 * the message is sent, so an edit has no attribute bag to get wrong and no key
 * that can be wiped by omission.
 *
 * *Whether* an edit is still allowed is not decided here: MessagePolicy::update
 * closes the window `petconnect.messaging.edit_window_minutes` after sending,
 * and MessageController calls it. The legacy app had no window at all — a
 * message could be rewritten indefinitely, including after the other side had
 * read and acted on it.
 *
 * Like SendMessage, this is where the masked word list is resolved from config,
 * so the step reads none.
 */
class UpdateMessage
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(Message $message, string $content): Message
    {
        /** @var list<string> $bannedWords */
        $bannedWords = config('bad-words.words', []);

        $context = new ReviseMessageContext(
            message: $message,
            content: $content,
            bannedWords: $bannedWords,
            mask: (string) config('bad-words.mask', '****'),
        );

        return $this->pipeline
            ->send($context)
            ->through([
                CleanContent::class,
                PersistContent::class,
            ])
            ->then(fn (ReviseMessageContext $completed): Message => $completed->message());
    }
}
