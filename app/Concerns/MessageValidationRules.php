<?php

namespace App\Concerns;

use App\Enums\MessageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules the message Form Requests share.
 *
 * `content` is the one key both the send and the edit form post, and the one
 * key App\Http\Resources\Message\MessageResource emits back under that exact
 * name, so it is held once here for the same reason CommentValidationRules
 * holds its own: the person most likely to break resource↔Form-Request parity
 * is whoever renames a key, and there is only one place to rename it.
 *
 * The ceiling comes from `petconnect.messaging.max_length` against a `text`
 * column, so the validator and the column agree by construction.
 *
 * `type` is validated through Rule::enum rather than a hand-written `in:` list,
 * so adding a MessageType case cannot leave the validator behind. It is
 * `sometimes` rather than `required`: an omitted type is a plain text message,
 * which is what the column defaults to and the only case the product sends
 * today. It carries no `present` rule — omission there means "text", a correct
 * answer rather than a silently wiped value, which is the distinction
 * .ai/rules/requests.md draws.
 */
trait MessageValidationRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function messageContentRules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.$this->maxContentLength()],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function messageTypeRules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(MessageType::class)],
        ];
    }

    /**
     * The bound a page that renders the message composer has to be told.
     *
     * The composer cannot enforce, or even display, a ceiling it has not been
     * given; it defaulted to a hardcoded 5000 that happened to match today's
     * config and would have drifted the moment either side changed. Shipped as
     * the `messageBounds` prop by Web\ConversationController::show, built from
     * the same accessor the `max:` rule is built from, so the two cannot
     * disagree.
     *
     * A one-key array rather than a bare integer, so adding a second bound
     * later is a key rather than a new prop and a frontend rename.
     *
     * @return array{max_length: int}
     */
    public function messageBounds(): array
    {
        return ['max_length' => $this->maxContentLength()];
    }

    /**
     * The longest message the application accepts, in characters.
     */
    public function maxContentLength(): int
    {
        return (int) config('petconnect.messaging.max_length', 5000);
    }
}
