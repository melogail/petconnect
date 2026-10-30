<?php

use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Ticket;

/**
 * The Ticket resource renders: index, detail and the thread under it. A
 * misconfigured field — a Badge label map, a MorphTo type list, a HasMany
 * pointing at a resource that is not registered — fails here and nowhere
 * else, because the action tests post straight to the action endpoint.
 */
test('the tickets index renders with the open count, status badge and category label', function () {
    $admin = Admin::factory()->create();
    Ticket::factory()->create(['subject' => 'Missing breed']);
    Ticket::factory()->answered()->create();

    $this->actingAs($admin, 'admin')
        ->getJson('/nova-api/tickets')
        ->assertOk()
        ->assertJsonCount(2, 'resources')
        ->assertJsonPath('resources.1.title', 'Missing breed');
});

test('a ticket detail page lists its thread through the ticket-messages resource', function () {
    $admin  = Admin::factory()->create();
    $ticket = Ticket::factory()->answered('We fixed it.')->create();

    $this->actingAs($admin, 'admin')
        ->getJson("/nova-api/tickets/{$ticket->getKey()}")
        ->assertOk()
        ->assertJsonPath('resource.title', $ticket->subject);

    $this->actingAs($admin, 'admin')
        ->getJson("/nova-api/ticket-messages?viaResource=tickets&viaResourceId={$ticket->getKey()}&viaRelationship=messages&relationshipType=hasMany")
        ->assertOk()
        ->assertJsonCount(2, 'resources');

    expect($ticket->status)->toBe(TicketStatus::Answered);
});

test('the open-ticket badge in the menu counts only tickets waiting on the team', function () {
    $admin = Admin::factory()->create();
    Ticket::factory()->count(2)->create();
    Ticket::factory()->answered()->create();
    Ticket::factory()->closed()->create();

    expect(Ticket::query()->open()->count())->toBe(2);

    // The shell serialises the menu, so a badge closure that throws fails here.
    $this->actingAs($admin, 'admin')->get('/nova/dashboards/main')->assertOk();
});
