<?php

use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Ticket;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Support\Facades\Notification;

const CHANGE_TICKET_STATUS_ACTION = '/nova-api/tickets/action?action=change-status';

/**
 * Moving a ticket without writing to it. Closing is the one transition that
 * tells the sender — it goes through CloseTicket, the single writer of
 * `closed_at` — and the others are the team correcting whose turn it is, so
 * they say nothing.
 */
test('closing from Nova stamps closed_at and tells the member, without a new message', function () {
    Notification::fake();

    $admin  = Admin::factory()->create();
    $ticket = Ticket::factory()->answered()->create();

    $this->actingAs($admin, 'admin')
        ->postJson(CHANGE_TICKET_STATUS_ACTION, [
            'resources' => [$ticket->getKey()],
            'status'    => TicketStatus::Closed->value,
        ])
        ->assertOk()
        ->assertJsonPath('message', '1 ticket(s) marked as Closed.');

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->closed_at)->not->toBeNull()
        ->and($ticket->messages()->count())->toBe(2);

    Notification::assertSentTo(
        $ticket->user,
        TicketRepliedNotification::class,
        fn (TicketRepliedNotification $notification): bool => $notification->reply === null
            && $notification->toArray($ticket->user)['message_key'] === 'notifications.ticket_closed',
    );
});

test('reopening a closed ticket clears closed_at and tells nobody', function () {
    Notification::fake();

    $admin  = Admin::factory()->create();
    $ticket = Ticket::factory()->closed()->create();

    $this->actingAs($admin, 'admin')
        ->postJson(CHANGE_TICKET_STATUS_ACTION, [
            'resources' => [$ticket->getKey()],
            'status'    => TicketStatus::Open->value,
        ])
        ->assertOk();

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->closed_at)->toBeNull();

    Notification::assertNothingSent();
});

test('a ticket cannot be edited through the Nova form while the actions still run', function () {
    $admin  = Admin::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/tickets/{$ticket->getKey()}", ['subject' => 'Rewritten'])
        ->assertForbidden();

    expect($ticket->fresh()->subject)->not->toBe('Rewritten');
});
