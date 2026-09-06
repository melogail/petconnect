<?php

namespace App\Http\Requests\Comment;

use App\Concerns\CommentValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new comment or reply.
 *
 * Authorization is deliberately not done here. Every comment route authorizes
 * through CommentPolicy with $this->authorize() in CommentController, per
 * .ai/rules/controllers.md; the legacy StoreCommentRequest implemented
 * authorize() as `auth()->check()` while its controller separately checked the
 * policy, so the same decision had two homes and the weaker one was the first
 * thing a reader met.
 *
 * `parent_id` is validated only as a shape here. Whether it names a real
 * comment, on *this* commentable, that is not itself a reply is decided by
 * Pipelines\Comments\PublishComment\ValidateParentBelongsToCommentable, in one
 * query, against the target the flow has already resolved.
 *
 * The legacy request answered the first two of those with a Rule::exists()
 * whose where() compared `commentable_type` against `$type->modelClass()`, and
 * it worked: the legacy app registered no morph map anywhere, so the column
 * held fully qualified class names too. What it had no clause for was depth — a
 * parent that was itself a reply passed, and legacy threads nested without
 * limit. It was also a second query against a row the service was about to
 * resolve again, with a window in between for the parent to move or vanish.
 *
 * Copying that rule into this request would not merely duplicate the step, it
 * would silently pass everything: a morph map *is* enforced here, so
 * `commentable_type` holds the alias `pet` and a where() on a class name
 * matches no row. Morph existence checks stay out of Form Requests for that
 * reason — see .ai/rules/app.md.
 *
 * `parent_id` carries no `present` rule: a comment posts JSON, so an empty
 * value is expressible, but an absent `parent_id` legitimately means "top-level
 * comment" rather than a key the client forgot. The `present` guard exists for
 * write bags where omission silently wipes a stored value, which a create never
 * does. See .ai/rules/requests.md.
 */
class StoreCommentRequest extends FormRequest
{
    use CommentValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->commentContentRules(),
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The submitted comment text.
     */
    public function content(): string
    {
        return (string) $this->validated('content');
    }

    /**
     * The comment being replied to, or null for a top-level comment.
     */
    public function parentId(): ?int
    {
        $parentId = $this->validated('parent_id');

        return $parentId === null ? null : (int) $parentId;
    }
}
