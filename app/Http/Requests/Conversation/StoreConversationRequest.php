<?php

namespace App\Http\Requests\Conversation;

use App\Concerns\MessageValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a request to open a direct conversation.
 *
 * Authorization is deliberately not done here. Every messaging route authorizes
 * through ConversationPolicy or MessagePolicy with $this->authorize() in the
 * controller, per .ai/rules/controllers.md. The legacy trio did the opposite and
 * did it inconsistently: StoreConversationRequest::authorize() was
 * `$this->user() !== null`, StoreMessageRequest and UpdateMessageRequest each
 * consulted a policy from inside authorize() while their controller called none
 * — so MessageController::store and ::update had no visible authorization at
 * all, and reading the controller told you nothing about who could reach them.
 *
 * `recipient_id`, not the legacy `other_user_id`: the key names the person the
 * conversation is with, and "other" only means anything relative to a viewer
 * the request does not describe.
 *
 * `Rule::notIn` is what rejects messaging yourself. The legacy rule was the
 * same shape written as `Rule::notIn([$this->user()->id])`, which dereferences
 * a null user on an unauthenticated request and fatals where a 401 belongs; the
 * id here is collected null-safely, so an absent user simply excludes nothing
 * and the route's `auth` middleware answers first. The pipeline re-checks it in
 * StartDirectConversation\EnsureDistinctParticipants for the callers that pass
 * no Form Request.
 *
 * Whether that recipient will *accept* a conversation is not asked here and
 * cannot be: it is a question about another user's settings, answered in
 * StartDirectConversation\EnsureRecipientAccepts against the model the flow has
 * already resolved. A Form Request that tried would be a second query
 * against a row the pipeline is about to load again, with a window in between —
 * the same trap .ai/rules/app.md records for morph existence checks.
 *
 * `initial_message` is optional and carries no `present` rule: this form posts
 * JSON, so an empty value is expressible, but an absent key legitimately means
 * "open the thread without saying anything yet", which the profile button
 * offers. `present` guards write bags where omission silently wipes a stored
 * value, which a create never does. See .ai/rules/requests.md.
 */
class StoreConversationRequest extends FormRequest
{
    use MessageValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipient_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::notIn(array_filter([$this->user()?->getKey()])),
            ],
            'initial_message' => ['nullable', 'string', 'max:'.$this->maxContentLength()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_id.not_in' => __('You cannot start a conversation with yourself.'),
        ];
    }

    /**
     * The user the conversation is being opened with.
     */
    public function recipientId(): int
    {
        return (int) $this->validated('recipient_id');
    }

    /**
     * The opening message, or null when the thread is being opened empty.
     */
    public function initialMessage(): ?string
    {
        $initialMessage = $this->validated('initial_message');

        return $initialMessage === null ? null : (string) $initialMessage;
    }
}
