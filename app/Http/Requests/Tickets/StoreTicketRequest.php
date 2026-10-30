<?php

namespace App\Http\Requests\Contact;

use App\Concerns\ContactValidationRules;
use App\Enums\TicketType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a message sent through the support page's contact form.
 *
 * The route is public, so the sender may be a guest or a member, and the two
 * send different forms: a guest fills in `name` and `email`, a member does
 * not see those fields. The rules follow the caller — see
 * ContactValidationRules for why a member's `name`/`email` are excluded rather
 * than prohibited.
 *
 * No `authorize()`: there is no policy-governed model here, and the Form
 * Request validates only (.ai/rules/controllers.md).
 */
class StoreContactRequest extends FormRequest
{
    use ContactValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->contactRules(identified: $this->user() !== null);
    }

    /**
     * The guest's name, or null for a member (the Action reads the account).
     */
    public function senderName(): ?string
    {
        $name = $this->validated('name');

        return $name === null ? null : (string) $name;
    }

    /**
     * The guest's reply address, or null for a member.
     */
    public function senderEmail(): ?string
    {
        $email = $this->validated('email');

        return $email === null ? null : (string) $email;
    }

    public function ticketType(): TicketType
    {
        return TicketType::from((string) $this->validated('ticket_type'));
    }

    public function subject(): string
    {
        return (string) $this->validated('subject');
    }

    public function message(): string
    {
        return (string) $this->validated('message');
    }
}
