<?php

use App\Actions\Tickets\ReplyToTicket;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * The member's side of the support desk under `settings/tickets`: the list of
 * headers, one ticket's thread, a follow-up and closing.
 *
 * The things worth pinning are the boundaries — a ticket is listed and shown
 * by `user_id`, so another member's ticket and an orphaned ticket carrying the
 * same address stay out; a reply from the member flips the ticket back to
 * Open and notifies nobody; a closed ticket takes no more writing.
 */
test('the tickets page lists the member own tickets as headers, most recently active first', function () {
    $member   = User::factory()->create(['email' => 'omar@example.com']);
    $older    = Ticket::factory()->for($member)->create(['last_activity_at' => now()->subDay()]);
    $answered = Ticket::factory()->for($member)->answered()->create();
    Ticket::factory()->create();
    Ticket::factory()->fromDeletedAccount()->create(['email' => 'omar@example.com']);

    $this->actingAs($member)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/Tickets')
            ->has('tickets.data', 2)
            ->where('tickets.data.0.id', $answered->getKey())
            ->where('tickets.data.0.status', TicketStatus::Answered->value)
            ->where('tickets.data.0.messages_count', 2)
            ->where('tickets.data.1.id', $older->getKey())
            ->where('tickets.data.1.messages_count', 1)
            ->missing('tickets.data.0.email')
            ->missing('tickets.data.0.messages')
            ->where('ticketBounds.max_message_length', 2000));
});

test('the tickets pages require a signed-in member', function () {
    $ticket = Ticket::factory()->create();

    $this->get(route('tickets.index'))->assertRedirect(route('login'));
    $this->get(route('tickets.show', $ticket))->assertRedirect(route('login'));
});

test('a ticket page shows the thread with each side named', function () {
    $member = User::factory()->create(['name' => 'Omar Said']);
    $ticket = Ticket::factory()->for($member)->answered('We added the breed.')->create();

    $this->actingAs($member)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/Ticket')
            ->where('ticket.id', $ticket->getKey())
            ->where('ticket.status', TicketStatus::Answered->value)
            ->where('canReply', true)
            ->has('messages', 2)
            ->where('messages.0.is_from_support', false)
            ->where('messages.0.author_name', 'Omar Said')
            ->where('messages.1.is_from_support', true)
            ->where('messages.1.body', 'We added the breed.'));
});

test('another member ticket is a 403, and an orphaned ticket carrying the same address too', function () {
    $member   = User::factory()->create(['email' => 'omar@example.com']);
    $other    = Ticket::factory()->create();
    $orphaned = Ticket::factory()->fromDeletedAccount()->create(['email' => 'omar@example.com']);

    $this->actingAs($member)->get(route('tickets.show', $other))->assertForbidden();
    $this->actingAs($member)->get(route('tickets.show', $orphaned))->assertForbidden();
    $this->actingAs($member)->post(route('tickets.reply', $other), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($member)->post(route('tickets.close', $other))->assertForbidden();
});

test('a follow-up from the member is appended, reopens the ticket and notifies nobody', function () {
    Notification::fake();

    $member = User::factory()->create();
    $ticket = Ticket::factory()->for($member)->answered()->create();

    $this->actingAs($member)
        ->from(route('tickets.show', $ticket))
        ->post(route('tickets.reply', $ticket), ['body' => 'It still does not show up for me.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tickets.show', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->messages()->count())->toBe(3);

    $this->assertDatabaseHas('ticket_messages', [
        'ticket_id'   => $ticket->getKey(),
        'author_type' => 'user',
        'author_id'   => $member->getKey(),
        'body'        => 'It still does not show up for me.',
    ]);

    Notification::assertNothingSent();
});

test('the member closes their own ticket and can no longer write to it', function () {
    Notification::fake();

    $member = User::factory()->create();
    $ticket = Ticket::factory()->for($member)->answered()->create();

    $this->actingAs($member)
        ->from(route('tickets.show', $ticket))
        ->post(route('tickets.close', $ticket))
        ->assertRedirect(route('tickets.show', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->closed_at)->not->toBeNull();

    Notification::assertNothingSent();

    $this->actingAs($member)
        ->post(route('tickets.reply', $ticket), ['body' => 'One more thing'])
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('canReply', false));

    expect(TicketMessage::query()->where('ticket_id', $ticket->getKey())->count())->toBe(2);
});

test('a reply from the team on the ticket model marks it answered and notifies the member', function () {
    Notification::fake();

    $member = User::factory()->create();
    $ticket = Ticket::factory()->for($member)->create();
    $admin  = Admin::factory()->create();

    app(ReplyToTicket::class)->handle($ticket, $admin, 'Fixed on our side.');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Answered);

    Notification::assertSentTo(
        $member,
        TicketRepliedNotification::class,
        fn (TicketRepliedNotification $notification, array $channels): bool => $channels === ['database', 'mail']
            && $notification->toArray($member)['message_key'] === 'notifications.ticket_answered'
            && $notification->toArray($member)['url'] === route('tickets.show', $ticket),
    );
});
