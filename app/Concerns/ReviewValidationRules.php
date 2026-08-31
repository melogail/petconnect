<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The rules the review Form Requests share, and the single place each review
 * key is spelled.
 *
 * ## `rate` is the rule the legacy app did not have
 *
 * The legacy ReviewController::store took a raw `Illuminate\Http\Request` and
 * passed `$request->rating` straight into `Review::create()`. There was no Form
 * Request on the store path at all, so nothing checked that a rating was an
 * integer, that it was inside 1-5, or that it was present — and `rate` is an
 * `unsignedTinyInteger`, which happily stores 0 and 99. A single reviewer could
 * therefore set any average rating on any profile. `min`/`max` here, driven by
 * `petconnect.reviews.min_rate` / `max_rate`, are that missing rule; the
 * frontend draws its star scale from the same two values, so the widget and the
 * validator cannot disagree.
 *
 * ## Where `present` goes, and where it does not
 *
 * `reviewReplacementRules()` adds `present` to `comment` for the update path.
 * A review PUT writes both columns, so an omitted `comment` would be written as
 * null and silently wipe what the author typed — the exact failure mode
 * .ai/rules/requests.md records for the pet form. `rate` needs no `present`
 * because it is already `required`.
 *
 * The create path uses `reviewRules()` without it: a create has nothing to
 * wipe, and an absent `comment` there legitimately means "a rating with no
 * words", the same reasoning that keeps `present` off `parent_id` in
 * CommentValidationRules.
 *
 * Reviews post JSON rather than multipart — there is no file input on this
 * form — so `present` is expressible here in a way it is not on the pet form.
 *
 * See .ai/rules/requests.md on resource↔Form-Request key parity: both keys
 * below are emitted by Http\Resources\Review\ReviewResource under exactly these
 * names, and the person most likely to break that is whoever renames one here.
 */
trait ReviewValidationRules
{
    /**
     * The review write bag, as a create submits it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reviewRules(): array
    {
        return [
            'rate' => ['required', 'integer', 'min:'.$this->minRate(), 'max:'.$this->maxRate()],
            'comment' => ['nullable', 'string', 'max:'.$this->maxCommentLength()],
        ];
    }

    /**
     * The same bag for a PUT, where an omitted `comment` would be a silent wipe
     * rather than an omission.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reviewReplacementRules(): array
    {
        $rules = $this->reviewRules();

        $rules['comment'] = ['present', ...$rules['comment']];

        return $rules;
    }

    /**
     * The bounds a page that renders the review form has to be told.
     *
     * The star widget cannot draw a scale it has not been given the length of,
     * and the counter under the comment box cannot show a limit it does not
     * know, so both were hardcoded on the client — five stars and a guess —
     * while the validator read config. This is the same three accessors the
     * rules above are built from, handed to Web\ProfileController::show as the
     * `reviewBounds` prop, which is what makes the config file's promise that
     * "the validator and the frontend scale move together" true rather than
     * aspirational.
     *
     * Snake_case keys matching the config, following the `filterBounds` prop
     * HomeController already ships.
     *
     * @return array{min_rate: int, max_rate: int, max_comment_length: int}
     */
    public function reviewBounds(): array
    {
        return [
            'min_rate' => $this->minRate(),
            'max_rate' => $this->maxRate(),
            'max_comment_length' => $this->maxCommentLength(),
        ];
    }

    /**
     * The lowest rating the application accepts.
     */
    public function minRate(): int
    {
        return (int) config('petconnect.reviews.min_rate', 1);
    }

    /**
     * The highest rating the application accepts.
     */
    public function maxRate(): int
    {
        return (int) config('petconnect.reviews.max_rate', 5);
    }

    /**
     * The longest review comment the application accepts, in characters.
     */
    public function maxCommentLength(): int
    {
        return (int) config('petconnect.reviews.max_comment_length', 1000);
    }
}
