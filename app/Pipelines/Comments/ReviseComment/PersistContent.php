<?php

namespace App\Pipelines\Comments\ReviseComment;

use Closure;

/**
 * Write the cleaned text back onto the comment.
 *
 * A single-column update, so there is no transaction here — one statement is
 * already atomic. PublishComment\PersistComment is a single INSERT and has none
 * either; the only transaction in this domain is the one DeleteCommentThread's
 * Action opens, where several rows have to land together.
 *
 * `content` is the only column touched. The legacy service passed the id back
 * through a repository (`update(int $id, array $data)`), which cost a second
 * SELECT to find a row the caller was already holding; the model is right here.
 */
class PersistContent
{
    public function handle(ReviseCommentContext $context, Closure $next): mixed
    {
        $context->comment->update([
            'content' => $context->content(),
        ]);

        return $next($context);
    }
}
