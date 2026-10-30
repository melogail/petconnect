<?php

namespace App\Http\Requests\Tickets;

use App\Concerns\TicketValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a member's follow-up on one of their tickets.
 *
 * No `authorize()`: whether the member may write to this ticket is
 * TicketPolicy::reply, asked by the controller (.ai/rules/controllers.md).
 */
class ReplyToTicketRequest extends FormRequest
{
    use TicketValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->replyRules();
    }

    public function body(): string
    {
        return (string) $this->validated('body');
    }
}
