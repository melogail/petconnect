<?php

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

/**
 * Opening a support ticket, `POST settings/tickets` — members only.
 *
 * The things worth pinning: a guest is sent to sign in and no row is written;
 * a member's row carries the account's name and address *whatever the request
 * said*, so a careless rewrite that trusts `name`/`email` from the payload
 * cannot let a sender put another address on the row; and a ticket is a
 * header plus its opening message, authored by the member.
 */

/**
 * @return array<string, string>
 */
function ticketPayload(): array
{
    return [
        'category' => TicketCategory::MissingBreed->value,
        'subject'  => 'Egyptian Mau is not in the cat breeds',
        'message'  => 'I could not find the breed when listing my cat.',
    ];
}

test('a guest cannot open a ticket and is sent to sign in', function () {
    $this->post(route('tickets.store'), ticketPayload() + [
        'name'  => 'Mona Khalil',
        'email' => 'mona@example.com',
    ])->assertRedirect(route('login'));

    expect(Ticket::count())->toBe(0);
});

test('a member opens a ticket from their account and lands on it', function () {
    $member   = User::factory()->create([
        'name'  => 'Omar Said',
        'email' => 'omar@example.com',
    ]);

    $response = $this->actingAs($member)
        ->from(route('tickets.index'))
        ->post(route('tickets.store'), ticketPayload())
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type'    => 'success',
            'message' => 'Your ticket has been opened. We will get back to you shortly.',
        ]);

    $ticket   = Ticket::query()->sole();

    $response->assertRedirect(route('tickets.show', $ticket));

    expect($ticket->user_id)->toBe($member->getKey())
        ->and($ticket->name)->toBe('Omar Said')
        ->and($ticket->email)->toBe('omar@example.com')
        ->and($ticket->category)->toBe(TicketCategory::MissingBreed)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->last_activity_at)->not->toBeNull();

    $this->assertDatabaseHas('ticket_messages', [
        'ticket_id'   => $ticket->getKey(),
        'author_type' => 'user',
        'author_id'   => $member->getKey(),
        'body'        => 'I could not find the breed when listing my cat.',
    ]);
});

test('a member cannot put another name or address on their ticket', function () {
    $member = User::factory()->create([
        'name'  => 'Omar Said',
        'email' => 'omar@example.com',
    ]);

    $this->actingAs($member)
        ->from(route('tickets.index'))
        ->post(route('tickets.store'), ticketPayload() + [
            'name'  => 'Somebody Else',
            'email' => 'else@example.com',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('tickets', [
        'user_id' => $member->getKey(),
        'name'    => 'Omar Said',
        'email'   => 'omar@example.com',
    ]);
    $this->assertDatabaseMissing('tickets', ['email' => 'else@example.com']);
});

test('the category must be a known one and the rest is required', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->from(route('tickets.index'))
        ->post(route('tickets.store'), [
            'category' => 'billing',
            'subject'  => '',
            'message'  => str_repeat('x', 2001),
        ])
        ->assertRedirect(route('tickets.index'))
        ->assertSessionHasErrors(['category', 'subject', 'message']);

    expect(Ticket::count())->toBe(0);
});
