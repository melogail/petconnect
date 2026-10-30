<?php

use App\Models\ContactMessage;

/**
 * The guest channel, `POST contact`: a one-off message with a name and a
 * reply address, stored for the back office and answered by email. There is
 * no account to lean on, so both identity fields are required of everyone.
 */
test('a guest sends a message with their name and address and is told it went', function () {
    $this->from(route('support'))
        ->post(route('contact.store'), [
            'name'    => 'Mona Khalil',
            'email'   => 'mona@example.com',
            'subject' => 'Egyptian Mau is not in the cat breeds',
            'message' => 'I could not find the breed when listing my cat.',
        ])
        ->assertRedirect(route('support'))
        ->assertInertiaFlash('toast', [
            'type'    => 'success',
            'message' => 'Your message has been sent. We will get back to you by email shortly.',
        ]);

    $this->assertDatabaseHas('contact_messages', [
        'name'    => 'Mona Khalil',
        'email'   => 'mona@example.com',
        'subject' => 'Egyptian Mau is not in the cat breeds',
        'message' => 'I could not find the breed when listing my cat.',
    ]);
});

test('a name, a valid address, a subject and a bounded message are all required', function () {
    $this->from(route('support'))
        ->post(route('contact.store'), [
            'email'   => 'not-an-address',
            'subject' => '',
            'message' => str_repeat('x', 2001),
        ])
        ->assertRedirect(route('support'))
        ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

    expect(ContactMessage::count())->toBe(0);
});
