<?php

namespace App\Concerns;

use App\Enums\TicketType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules the support contact form accepts, and the single place each key
 * is spelled — the Form Request and the Nova resource both read them here.
 *
 * ## `name` and `email` depend on who is asking
 *
 * A guest has to say who they are, so both are required. A signed-in member is
 * already known: the form hides both fields and the Action copies them off the
 * account, so anything a member *does* send under those keys is excluded from
 * the validated payload rather than trusted. `exclude` is the right verb — a
 * `prohibited` would 422 a stale tab that still rendered the guest form, and
 * `nullable` would leave the controller a key it must remember to ignore.
 */
trait ContactValidationRules
{
    /**
     * The whole form, for the given kind of sender.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function contactRules(bool $identified): array
    {
        return [
            'name'        => $identified ? ['exclude'] : $this->contactNameRules(),
            'email'       => $identified ? ['exclude'] : $this->contactEmailRules(),
            'ticket_type' => $this->ticketTypeRules(),
            'subject'     => $this->subjectRules(),
            'message'     => $this->contactMessageRules(),
        ];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function contactNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function contactEmailRules(): array
    {
        return ['required', 'string', 'email', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function ticketTypeRules(): array
    {
        return ['required', Rule::enum(TicketType::class)];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function subjectRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function contactMessageRules(): array
    {
        return ['required', 'string', 'max:'.$this->maxContactMessageLength()];
    }

    /**
     * The longest support message the application accepts, in characters.
     */
    public function maxContactMessageLength(): int
    {
        return (int) config('petconnect.support.max_message_length', 2000);
    }
}
