<?php

namespace App\Pipelines\Comments\ReviseComment;

use App\Models\Comment;
use App\Pipelines\Comments\CommentContentContext;

/**
 * Passable for the edit-a-comment flow.
 *
 * The flow directory is `ReviseComment` rather than `UpdateComment` because
 * App\Actions\Comments\UpdateComment is the class that runs it, and
 * .ai/rules/pipelines.md forbids a flow namespace that reads identically to an
 * Action class name.
 *
 * Only the text is editable: the author, the target and the parent are settled
 * when the comment is published and no edit reopens them. That is why this
 * context adds a Comment and nothing else — there is no attribute bag here, and
 * an omitted key cannot silently rewrite anything, which is the trap the pet
 * update flow has to guard against with `present` rules.
 */
class ReviseCommentContext extends CommentContentContext
{
    /**
     * @param  list<string>  $bannedWords
     */
    public function __construct(
        public readonly Comment $comment,
        string $content,
        array $bannedWords = [],
        string $mask = '****',
    ) {
        parent::__construct($content, $bannedWords, $mask);
    }
}
