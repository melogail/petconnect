<?php

namespace App\Http\Requests\Contact;

use App\Concerns\TicketValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a guest's message from the support page's contact form.
 *
 * Name and address are always required: there is no account to read them
 * off. The field rules are shared with the ticket form through
 * TicketValidationRules so the two composers accept the same lengths.
 *
 * No `authorize()`: there is no policy-governed model here, and the Form
 * Request validates only (.ai/rules/controllers.md).
 */
class StoreContactRequest extends FormRequest
{
    use TicketValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->contactRules();
    }

    public function senderName(): string
    {
        return (string) $this->validated('name');
    }

    public function senderEmail(): string
    {
        return (string) $this->validated('email');
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
