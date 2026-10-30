<?php

namespace App\Http\Requests\Tickets;

use App\Concerns\TicketValidationRules;
use App\Enums\TicketCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a member's new support ticket.
 *
 * No name or address: the route is behind `auth` and the account supplies
 * both (Actions\Tickets\OpenTicket). A guest's message is a different form
 * and a different model — Contact\StoreContactRequest.
 *
 * No `authorize()`: there is no policy-governed model here, and the Form
 * Request validates only (.ai/rules/controllers.md).
 */
class StoreTicketRequest extends FormRequest
{
    use TicketValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->ticketRules();
    }

    public function category(): TicketCategory
    {
        return TicketCategory::from((string) $this->validated('category'));
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
