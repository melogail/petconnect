<?php

namespace App\Http\Requests\Message;

use App\Concerns\MessageValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an edit to a message.
 *
 * `content` is the only writable column: an edit cannot move a message to
 * another conversation, reassign its sender, change its type, or pin it. That
 * is why this request accepts one key and why a PUT here is not the
 * full-replacement hazard a pet PUT is — there is no attribute bag for an
 * omitted key to null out.
 *
 * Authorization — that the editor is the sender, and that the edit window is
 * still open — is MessagePolicy::update, called from MessageController. The
 * legacy UpdateMessageRequest consulted the policy from inside authorize()
 * while its controller called none, so the check was invisible at the call
 * site; and the legacy policy had no window in it, so a message could be
 * rewritten indefinitely, long after the other side had read it.
 */
class UpdateMessageRequest extends FormRequest
{
    use MessageValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->messageContentRules();
    }

    /**
     * The submitted message text.
     */
    public function content(): string
    {
        return (string) $this->validated('content');
    }
}
