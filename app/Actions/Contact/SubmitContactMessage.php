<?php

namespace App\Actions\Contact;

use App\Enums\TicketType;
use App\Models\Contact;
use App\Models\User;

/**
 * Record a message sent through the support page's contact form.
 *
 * A member's identity comes off the account, never off the request: the form
 * hides the name and email fields for a signed-in sender and the Form Request
 * excludes them, so `$name` and `$email` are only consulted for a guest. The
 * row still carries both columns for a member so the back office reads every
 * message the same way, and so a message outlives a later change of address
 * or a deleted account (`user_id` is nulled on delete).
 *
 * Throttling is a named limiter on the route (`throttle:contact-messages`),
 * keyed by IP because the route is public.
 */
class SubmitContactMessage
{
    public function handle(
        ?User $sender,
        ?string $name,
        ?string $email,
        TicketType $ticketType,
        string $subject,
        string $message,
    ): Contact {
        return Contact::create([
            'user_id'     => $sender?->getKey(),
            'name'        => $sender?->name ?? (string) $name,
            'email'       => $sender?->email ?? (string) $email,
            'ticket_type' => $ticketType,
            'subject'     => $subject,
            'message'     => $message,
        ]);
    }
}
