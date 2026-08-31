<?php

namespace App\Exceptions\Reviews;

use Illuminate\Validation\ValidationException;

/**
 * The reviewer is one of the people the review would be about.
 *
 * The legacy app had no such check anywhere — no policy, no request rule, no
 * guard in CreateReviewAction — so any account could rate itself five stars as
 * many times as it liked, and with no unique constraint on `reviews` either,
 * repeatedly.
 *
 * Which users a review is "about" is the target's own answer
 * (App\Contracts\Reviewable::reviewSubjects()), so this holds for every case on
 * App\Enums\Reviewable rather than for the one the check was written against.
 *
 * ## The error key
 *
 * `review`, not `rate` or `comment`. Neither of those fields is wrong — the
 * submission as a whole is — and the target is a URL segment rather than a form
 * control, so there is no input the client could highlight. A single
 * flow-level key gives the form somewhere to render the message
 * (`errors.review`) without pointing at a field the user should change.
 *
 * ValidationException is the base per .ai/rules/pipelines.md: the abort is
 * attributable to what was submitted, and Laravel already renders it as a 422
 * with `message`/`errors` for an XHR caller and a redirect-back-with-errors for
 * a form post. A bespoke exception plus a `render` mapping would be two more
 * moving parts for byte-identical behaviour.
 */
class CannotReviewSelf extends ValidationException
{
    public static function make(): self
    {
        return self::withMessages([
            'review' => __('You cannot review yourself.'),
        ]);
    }
}
