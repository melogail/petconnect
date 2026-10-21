<?php

use App\Enums\TicketType;
use App\Models\Contact;
use App\Models\User;

/**
 * The support page's contact form, `POST contact`.
 *
 * The route is public and the sender decides the form: a guest has to say who
 * they are, a member is already known. The two halves of that split are the
 * things worth pinning — a guest row that carries what they typed, and a
 * member row that carries the account's name and address *whatever the
 * request said*. The second is the one a careless rewrite loses: a controller
 * that trusts `name`/`email` whenever they are present lets a signed-in sender
 * put another address on the row.
 */

/**
 * @return array<string, string>
 */
function contactPayload(): array
{
    return [
        'ticket_type' => TicketType::MissingBreed->value,
        'subject'     => 'Egyptian Mau is not in the cat breeds',
        'message'     => 'I could not find the breed when listing my cat.',
    ];
}

test('a guest sends a message with their name and address and is told it went', function () {
    $this->from(route('support'))
        ->post(route('contact.store'), contactPayload() + [
            'name'  => 'Mona Khalil',
            'email' => 'mona@example.com',
        ])
        ->assertRedirect(route('support'))
        ->assertInertiaFlash('toast', [
            'type'    => 'success',
            'message' => 'Your message has been sent. We will get back to you shortly.',
        ]);

    $this->assertDatabaseHas('contacts', [
        'user_id'     => null,
        'name'        => 'Mona Khalil',
        'email'       => 'mona@example.com',
        'ticket_type' => TicketType::MissingBreed->value,
        'subject'     => 'Egyptian Mau is not in the cat breeds',
        'message'     => 'I could not find the breed when listing my cat.',
    ]);
});

test('a guest must give a name and a reply address', function () {
    $this->from(route('support'))
        ->post(route('contact.store'), contactPayload())
        ->assertRedirect(route('support'))
        ->assertSessionHasErrors(['name', 'email']);

    expect(Contact::count())->toBe(0);
});

test('a member sends a message without naming themselves and the row carries their account', function () {
    $member = User::factory()->create([
        'name'  => 'Omar Said',
        'email' => 'omar@example.com',
    ]);

    $this->actingAs($member)
        ->from(route('support'))
        ->post(route('contact.store'), contactPayload())
        ->assertRedirect(route('support'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('contacts', [
        'user_id'     => $member->getKey(),
        'name'        => 'Omar Said',
        'email'       => 'omar@example.com',
        'ticket_type' => TicketType::MissingBreed->value,
    ]);
});

test('a member cannot put another name or address on their message', function () {
    $member = User::factory()->create([
        'name'  => 'Omar Said',
        'email' => 'omar@example.com',
    ]);

    $this->actingAs($member)
        ->from(route('support'))
        ->post(route('contact.store'), contactPayload() + [
            'name'  => 'Somebody Else',
            'email' => 'else@example.com',
        ])
        ->assertRedirect(route('support'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('contacts', [
        'user_id' => $member->getKey(),
        'name'    => 'Omar Said',
        'email'   => 'omar@example.com',
    ]);
    $this->assertDatabaseMissing('contacts', ['email' => 'else@example.com']);
});

test('the topic must be one the back office knows, and the rest is required', function () {
    $this->from(route('support'))
        ->post(route('contact.store'), [
            'name'        => 'Mona Khalil',
            'email'       => 'not-an-address',
            'ticket_type' => 'Technical Issue',
            'subject'     => '',
            'message'     => str_repeat('x', 2001),
        ])
        ->assertRedirect(route('support'))
        ->assertSessionHasErrors(['email', 'ticket_type', 'subject', 'message']);

    expect(Contact::count())->toBe(0);
});
