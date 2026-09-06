<?php

namespace App\Exceptions\Reviews;

use Illuminate\Validation\ValidationException;

/**
 * This author has already reviewed this target.
 *
 * Thrown from two places on purpose, and they are not redundant:
 *
 * - SubmitReview\EnsureNotAlreadyReviewed is the friendly fast path. It answers
 *   before anything is written and costs one `exists()`.
 * - SubmitReview\PersistReview catches the database's own refusal. `reviews` is
 *   unique on (user_id, reviewable_type, reviewable_id), and that index — not
 *   the check above it — is the guarantee: two concurrent submissions both read
 *   "no review yet" and both proceed, and only the index can settle which one
 *   lands. Converting the violation here is what turns the loser of that race
 *   from a 500 into the same field error the fast path produces.
 *
 * The legacy app had neither: no constraint on the table and no application
 * check, so a user could review the same profile without limit.
 *
 * See CannotReviewSelf for why the key is `review` and why ValidationException
 * is the right base.
 */
class AlreadyReviewed extends ValidationException
{
    public static function make(): self
    {
        return self::withMessages([
            'review' => __('You have already reviewed this.'),
        ]);
    }
}
