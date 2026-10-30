<?php

use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Ticket;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

const REPLY_TO_TICKET_ACTION = '/nova-api/tickets/action?action=reply-to-ticket';

/**
 * The team's reply from Nova: appended to the thread as the admin's message,
 * the ticket marked Answered, the sender told. Two recipients, two channels:
 * the member gets a bell row and an email; once their account is deleted the
 * address on the row gets the email alone.
 *
 * TicketPolicy's `update` is false, so the action only runs because
 * `runAction` is true — the first test is also the pin on that line.
 */
test('replying to a member ticket appends the admin message, marks it answered and notifies them', function () {
    Notification::fake();

    $admin  = Admin::factory()->create();
    $ticket = Ticket::factory()->create(['subject' => 'Missing breed']);

    $this->actingAs($admin, 'admin')
        ->postJson(REPLY_TO_TICKET_ACTION, [
            'resources' => [$ticket->getKey()],
            'reply'     => 'We added the Egyptian Mau today.',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Replied to 1 ticket(s); the sender(s) have been notified.');

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Answered)
        ->and($ticket->messages()->count())->toBe(2);

    $this->assertDatabaseHas('ticket_messages', [
        'ticket_id'   => $ticket->getKey(),
        'author_type' => 'admin',
        'author_id'   => $admin->getKey(),
        'body'        => 'We added the Egyptian Mau today.',
    ]);

    Notification::assertSentTo(
        $ticket->user,
        TicketRepliedNotification::class,
        function (TicketRepliedNotification $notification, array $channels) use ($ticket): bool {
            $payload = $notification->toArray($ticket->user);

            return $channels === ['database', 'mail']
                && $payload['message_key'] === 'notifications.ticket_answered'
                && $payload['message_replace'] === ['subject' => 'Missing breed']
                && $payload['url'] === route('tickets.show', $ticket);
        },
    );
});

test('replying to a ticket whose account is gone mails the address on the row', function () {
    Notification::fake();

    $admin  = Admin::factory()->create();
    $ticket = Ticket::factory()->fromDeletedAccount()->create(['email' => 'mona@example.com']);

    $this->actingAs($admin, 'admin')
        ->postJson(REPLY_TO_TICKET_ACTION, [
            'resources' => [$ticket->getKey()],
            'reply'     => 'Thanks, fixed.',
        ])
        ->assertOk();

    Notification::assertSentOnDemand(
        TicketRepliedNotification::class,
        fn (TicketRepliedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail']
            && $notifiable->routes['mail'] === 'mona@example.com',
    );
});

test('an empty reply is refused and nothing moves', function () {
    Notification::fake();

    $admin  = Admin::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson(REPLY_TO_TICKET_ACTION, [
            'resources' => [$ticket->getKey()],
            'reply'     => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reply');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Open)
        ->and($ticket->messages()->count())->toBe(1);
    Notification::assertNothingSent();
});

test('the mail carries the reply and, for a member, a link to the ticket', function () {
    $ticket     = Ticket::factory()->answered('Fixed it.')->create(['subject' => 'Broken map']);
    $reply      = $ticket->messages()->latest('id')->first();

    $mail       = (new TicketRepliedNotification($ticket, $reply))->toMail($ticket->user);

    expect($mail->subject)->toBe('Support replied to your ticket "Broken map"')
        ->and($mail->introLines)->toContain('Fixed it.')
        ->and($mail->actionUrl)->toBe(route('tickets.show', $ticket));

    $orphanMail = (new TicketRepliedNotification($ticket, $reply))->toMail(new AnonymousNotifiable);

    expect($orphanMail->actionUrl)->toBeNull();
});
