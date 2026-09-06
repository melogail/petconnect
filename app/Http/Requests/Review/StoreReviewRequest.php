<?php

namespace App\Http\Requests\Review;

use App\Concerns\ReviewValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new review.
 *
 * There was no Form Request on this path at all in the legacy app. The
 * controller took a raw `Illuminate\Http\Request` and read `$request->rating`
 * and `$request->comment` off it directly, so a review could be filed with no
 * rating, a rating of 0 or 99, a non-numeric rating, or a comment of unbounded
 * length. This class is that missing validation; the 1-5 bound and the length
 * ceiling live in App\Concerns\ReviewValidationRules so the store and update
 * forms cannot disagree about them.
 *
 * The target is not validated here and has no key: it is
 * `POST reviews/{reviewable_type}/{reviewable_id}`, with `{reviewable_type}`
 * bound to App\Enums\Reviewable at the router, so an unknown type is a 404
 * before this request is constructed. Whether the target exists, is visible,
 * belongs to the reviewer, or has already been reviewed by them is decided in
 * Pipelines\Reviews\SubmitReview against the model the flow has resolved —
 * one query, on the row that will actually be written against, rather than a
 * `Rule::exists()` racing the insert.
 *
 * Authorization is deliberately not done here. Every review route authorizes
 * through ReviewPolicy with $this->authorize() in ReviewController, per
 * .ai/rules/controllers.md.
 *
 * `comment` carries no `present` rule: a create has nothing to wipe, and an
 * absent comment legitimately means "a rating with no words". The update
 * request does carry it — see UpdateReviewRequest.
 */
class StoreReviewRequest extends FormRequest
{
    use ReviewValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reviewRules();
    }

    /**
     * The submitted rating.
     */
    public function rate(): int
    {
        return (int) $this->validated('rate');
    }

    /**
     * The submitted comment, or null for a rating with no words.
     */
    public function comment(): ?string
    {
        $comment = $this->validated('comment');

        return $comment === null ? null : (string) $comment;
    }
}
