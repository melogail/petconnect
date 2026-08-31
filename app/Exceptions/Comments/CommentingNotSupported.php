<?php

namespace App\Exceptions\Comments;

use App\Contracts\Commentable as CommentableContract;
use App\Enums\Commentable as CommentableType;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The publish flow resolved a target that does not accept comments.
 *
 * This is a programming error, never user input: App\Enums\Commentable is a
 * closed whitelist maintained in code, so reaching this means a case was added
 * for a model that never implemented App\Contracts\Commentable. Left unchecked
 * the flow would happily write comment rows against a model with no thread to
 * read them back from, and nobody would notice until the payload was empty.
 *
 * It is therefore a LogicException and not a ValidationException: there is no
 * field the user could correct, and .ai/rules/pipelines.md reserves the
 * ValidationException base for aborts that really are field-level input
 * problems.
 */
class CommentingNotSupported extends LogicException
{
    public static function for(Model $commentable): self
    {
        return new self(sprintf(
            '[%s] is registered in %s but does not implement %s, so it cannot hold a comment thread.',
            $commentable::class,
            CommentableType::class,
            CommentableContract::class,
        ));
    }
}
