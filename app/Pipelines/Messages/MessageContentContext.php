<?php

namespace App\Pipelines\Messages;

/**
 * Shared passable for every flow that writes message text.
 *
 * Sending and revising differ in everything else — one resolves a conversation,
 * checks participation and notifies; the other touches one column — but both
 * put the same user-written string through the same cleaning, which is what
 * lets Shared\CleanContent be one step used by both rather than a copy in each.
 * It mirrors Pipelines\Comments\CommentContentContext exactly, and deliberately
 * does not extend or reuse it: .ai/rules/pipelines.md is explicit that a step
 * in a flow directory hints its own flow's context, and a messaging step
 * hinting a comments context would advertise that a comment flow may run it.
 *
 * The work underneath is shared instead, one level down:
 * App\Actions\Content\SanitizeContent is domain-free and both domains' steps
 * call it. That is the reuse that matters — one definition of what "clean" is —
 * without the two domains' pipelines becoming substitutable for one another.
 *
 * `bannedWords` and `mask` arrive already resolved from config by the Action
 * that runs the flow, so no step reads config() and either flow can be driven
 * with an explicit list from a test or the console.
 *
 * ## Behaviour change from the legacy app, stated deliberately
 *
 * Verified in petconnect-old: MessageService::send() and ::update() wrote
 * `content` straight through the repository, while CommentService ran the
 * submitted text through TrimContentPipeline and FilterBadWordsPipeline. So
 * private messages bypassed the filter comments went through. Running it here
 * changes that on purpose: the filter is a mask, not a moderation decision, and
 * a word the app will not print on a public thread is not a word it should
 * print in a private one either. The masking is also whole-word and
 * Unicode-aware now (see SanitizeContent), so the false positives that made the
 * legacy filter unpleasant — "class", "grass" — do not apply.
 */
abstract class MessageContentContext
{
    /**
     * @param  list<string>  $bannedWords
     */
    public function __construct(
        protected string $content,
        public readonly array $bannedWords = [],
        public readonly string $mask = '****',
    ) {}

    /**
     * The message text as the flow has it so far.
     */
    public function content(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }
}
