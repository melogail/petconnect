<?php

namespace App\Http\Requests\Comment;

use App\Concerns\CommentValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an edit to a comment.
 *
 * `content` is the only writable column: an edit cannot move a comment to
 * another listing, reassign its author, or turn it into a reply. That is why
 * this request accepts one key and why a PUT here is not the full-replacement
 * hazard a pet PUT is — there is no attribute bag for an omitted key to null
 * out.
 *
 * Authorization — that the editor is the author — is CommentPolicy::update,
 * called from CommentController. The legacy UpdateCommentRequest implemented
 * authorize() and consulted the policy from inside it, while the legacy
 * StorePetRequest next door returned true and left its controller to check:
 * one convention, one place to audit. See .ai/rules/controllers.md.
 */
class UpdateCommentRequest extends FormRequest
{
    use CommentValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->commentContentRules();
    }

    /**
     * The submitted comment text.
     */
    public function content(): string
    {
        return (string) $this->validated('content');
    }
}
