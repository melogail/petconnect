<?php

namespace App\Exceptions\Comments;

use Illuminate\Validation\ValidationException;

/**
 * A reply named a parent comment it is not allowed to hang off.
 *
 * Three separate ways to get here, all of them a stale form control rather than
 * an attack the request can spell out on its own:
 *
 * 1. The parent does not exist — deleted between the page render and the post,
 *    or a guessed id.
 * 2. The parent lives on a different commentable — a guessed or stale id from
 *    another listing's thread.
 * 3. The parent is itself a reply. Threads are two levels deep by design (see
 *    Pipelines\Comments\PublishComment\ValidateParentBelongsToCommentable), and
 *    a grandchild would be written to a row nothing ever reads back. This is
 *    the case the legacy `parent_id` rule had no clause for at all.
 *
 * It extends ValidationException so Laravel renders it as a field error on
 * `parent_id`, which is the control the client can actually act on — 422 with
 * `message`/`errors` for an XHR caller, redirect-back-with-errors for a form
 * post. .ai/rules/pipelines.md allows that base for exactly this case: the
 * abort is attributable to a submitted field.
 */
class InvalidParentComment extends ValidationException
{
    /**
     * The parent is missing, or belongs to a different commentable.
     */
    public static function notOnThisThread(): self
    {
        return self::withMessages([
            'parent_id' => __('The comment you are replying to is no longer part of this discussion.'),
        ]);
    }

    /**
     * The parent is a reply, and replies cannot themselves be replied to.
     */
    public static function isItselfAReply(): self
    {
        return self::withMessages([
            'parent_id' => __('Replies cannot be replied to; answer the original comment instead.'),
        ]);
    }
}
