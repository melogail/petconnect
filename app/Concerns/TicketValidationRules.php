<?php

namespace App\Concerns;

use App\Enums\TicketCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules the support desk accepts, and the single place each key is
 * spelled — the three Form Requests and both Nova resources read them here.
 *
 * Two forms share them. A member opens a **ticket** (`ticketRules()`): no
 * name or address, because the account supplies both and Actions\Tickets\
 * OpenTicket copies them off it. A guest sends a **contact message**
 * (`contactRules()`): name and address required, no category, because there
 * is no account and no thread. The same `max:` on the body keeps the two
 * composers' `maxlength` honest against one config key.
 */
trait TicketValidationRules
{
    /**
     * A member's "open a ticket" form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function ticketRules(): array
    {
        return [
            'category' => $this->categoryRules(),
            'subject'  => $this->subjectRules(),
            'message'  => $this->messageBodyRules(),
        ];
    }

    /**
     * A member's follow-up on an existing ticket.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function replyRules(): array
    {
        return [
            'body' => $this->messageBodyRules(),
        ];
    }

    /**
     * A guest's contact form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function contactRules(): array
    {
        return [
            'name'    => $this->senderNameRules(),
            'email'   => $this->senderEmailRules(),
            'subject' => $this->subjectRules(),
            'message' => $this->messageBodyRules(),
        ];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function senderNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function senderEmailRules(): array
    {
        return ['required', 'string', 'email', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function categoryRules(): array
    {
        return ['required', Rule::enum(TicketCategory::class)];
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
    protected function messageBodyRules(): array
    {
        return ['required', 'string', 'max:'.$this->maxTicketMessageLength()];
    }

    /**
     * The longest support message the application accepts, in characters.
     * Shipped to the pages as `ticketBounds.max_message_length` so the
     * composers' `maxlength` cannot disagree with the rule.
     */
    public function maxTicketMessageLength(): int
    {
        return (int) config('petconnect.support.max_message_length', 2000);
    }

    /**
     * @return array{max_message_length: int}
     */
    public function ticketBounds(): array
    {
        return ['max_message_length' => $this->maxTicketMessageLength()];
    }
}
