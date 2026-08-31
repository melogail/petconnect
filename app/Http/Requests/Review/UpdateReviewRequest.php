<?php

namespace App\Http\Requests\Review;

use App\Concerns\ReviewValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an edit to a review.
 *
 * `rate` and `comment` are the only writable columns: an edit cannot move a
 * review to another profile or reassign its author, so there is no key here
 * whose omission could hand the review to somebody else.
 *
 * A review PUT *is* a replacement of those two columns, though, which is why
 * `comment` carries `present` — see reviewReplacementRules() in
 * App\Concerns\ReviewValidationRules. Without it, a client that omitted
 * `comment` would have its stored text written to null silently, which is the
 * failure mode .ai/rules/requests.md records against the pet form. Reviews post
 * JSON rather than multipart, so an explicit empty value is expressible and the
 * guard is safe to apply here.
 *
 * Authorization — that the editor is the review's author — is
 * ReviewPolicy::update, called from ReviewController. The legacy
 * ReviewController::update did call `Gate::authorize('update', $review)`, which
 * resolved to the abstract App\Policies\Policy base and did check authorship;
 * what it had no validation for at all was the payload it then wrote.
 */
class UpdateReviewRequest extends FormRequest
{
    use ReviewValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reviewReplacementRules();
    }

    /**
     * The submitted rating.
     */
    public function rate(): int
    {
        return (int) $this->validated('rate');
    }

    /**
     * The submitted comment, or null when the author cleared it.
     */
    public function comment(): ?string
    {
        $comment = $this->validated('comment');

        return $comment === null ? null : (string) $comment;
    }
}
