<?php

namespace App\Exceptions\Reviews;

use App\Contracts\Reviewable as ReviewableContract;
use App\Enums\Reviewable as ReviewableType;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A review flow resolved a target that cannot be reviewed.
 *
 * This is a programming error, never user input: App\Enums\Reviewable is a
 * closed whitelist maintained in code and bound at the router, so reaching this
 * means a case was added for a model that never implemented
 * App\Contracts\Reviewable. Left unchecked the flow would write review rows
 * against a model that can say neither who the rating is about nor who may not
 * write it — which would silently reopen the self-review hole for that type
 * alone.
 *
 * A LogicException and not a ValidationException, for the same reason
 * App\Exceptions\Comments\CommentingNotSupported is: there is no field the user
 * could correct, and .ai/rules/pipelines.md reserves the ValidationException
 * base for aborts that really are field-level input problems.
 */
class ReviewingNotSupported extends LogicException
{
    public static function for(Model $reviewable): self
    {
        return new self(sprintf(
            '[%s] is registered in %s but does not implement %s, so it cannot be reviewed.',
            $reviewable::class,
            ReviewableType::class,
            ReviewableContract::class,
        ));
    }
}
