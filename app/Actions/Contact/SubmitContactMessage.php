<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;

/**
 * Record a guest's message from the support page's contact form.
 *
 * Nothing else happens here: the row is the inbox. The team reads it in Nova
 * and replies from their mail client, so there is no notification and no
 * status. A member does not come this way — they open a Ticket
 * (Actions\Tickets\OpenTicket), which has a thread and a page.
 *
 * Throttling is a named limiter on the route (`throttle:contact-messages`),
 * keyed by IP because the route is public.
 */
class SubmitContactMessage
{
    public function handle(string $name, string $email, string $subject, string $message): ContactMessage
    {
        return ContactMessage::create([
            'name'    => $name,
            'email'   => $email,
            'subject' => $subject,
            'message' => $message,
        ]);
    }
}
