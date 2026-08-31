<?php

namespace App\Pipelines\Comments;

/**
 * Shared passable for every flow that writes comment text.
 *
 * Publishing and revising differ in almost everything — one resolves a target,
 * checks a parent and notifies; the other only touches one column — but both
 * put the same user-written string through the same cleaning. Holding that
 * string here is what lets Shared\CleanContent be one step used by both flows
 * rather than a copy in each, mirroring the way the pet Normalize* steps are
 * shared through PetAttributeContext.
 *
 * `bannedWords` and `mask` arrive already resolved from config by the Action
 * that runs the flow, so no step reads config() and either flow can be driven
 * with an explicit list from a test or the console.
 */
abstract class CommentContentContext
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
     * The comment text as the flow has it so far.
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
