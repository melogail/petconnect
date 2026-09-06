<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Pipelines\Comments\ReviseComment\PersistContent;
use App\Pipelines\Comments\ReviseComment\ReviseCommentContext;
use App\Pipelines\Comments\Shared\CleanContent;
use Illuminate\Pipeline\Pipeline;

/**
 * Apply an edit to a comment.
 *
 * Two steps is a short pipeline, and .ai/rules/pipelines.md says to default to
 * inline work — but the first of those steps is Shared\CleanContent, and
 * running it here is the whole point: it is the same class the publish flow
 * runs, so a comment cannot be edited around the filter it was published
 * through. That was live in the legacy app in a smaller way (the same two
 * pipelines were listed twice, in createComment and updateComment) and would
 * have drifted the first time one list was changed.
 *
 * Only `content` is writable. The author, the target and the parent are settled
 * at publish time, so an edit has no attribute bag to get wrong and no key that
 * can be wiped by omission.
 *
 * Like CreateComment, this is where the masked word list is resolved from
 * config, so the step reads none.
 */
class UpdateComment
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(Comment $comment, string $content): Comment
    {
        /** @var list<string> $bannedWords */
        $bannedWords = config('bad-words.words', []);

        $context = new ReviseCommentContext(
            comment: $comment,
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
            ->then(fn (ReviseCommentContext $completed): Comment => $completed->comment);
    }
}
