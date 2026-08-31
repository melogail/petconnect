<?php

namespace App\Http\Requests\Message;

use App\Concerns\MessageValidationRules;
use App\Enums\MessageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new message in an existing conversation.
 *
 * Authorization is deliberately not done here. The legacy StoreMessageRequest
 * put it in authorize() — `$this->user()?->can('view', $conversation)` read off
 * the route — while MessageController::store called no policy at all, so the
 * only check on sending a message lived in a file the controller never
 * mentions. It is now MessagePolicy::create, called with
 * $this->authorize('create', [Message::class, $conversation]) at the top of the
 * action, per .ai/rules/controllers.md: one convention, one place to audit.
 *
 * Which conversation the message goes into is not validated here either — it is
 * the route parameter, resolved by model binding, and a soft-deleted
 * conversation does not bind.
 *
 * Neither key carries `present`. `content` is `required`, which already makes
 * an omission a 422; an absent `type` means "text", which is a correct answer
 * rather than a silently wiped value. See .ai/rules/requests.md.
 */
class StoreMessageRequest extends FormRequest
{
    use MessageValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->messageContentRules(),
            ...$this->messageTypeRules(),
        ];
    }

    /**
     * The submitted message text.
     */
    public function content(): string
    {
        return (string) $this->validated('content');
    }

    /**
     * The payload type, defaulting to text when the client did not name one.
     *
     * Returned as the enum rather than the string, so nothing downstream has to
     * decide what a raw wire value means: Pipelines\Messages\Send\PersistMessage
     * writes a MessageType case and the column's own default never has to be
     * relied on.
     */
    public function type(): MessageType
    {
        $type = $this->validated('type');

        return $type === null ? MessageType::Text : MessageType::from((string) $type);
    }
}
