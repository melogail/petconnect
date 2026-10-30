<?php

use App\Models\Admin;
use App\Models\ContactMessage;

/**
 * The guest inbox renders and behaves as an inbox: readable, deletable once
 * dealt with, never editable — the sender's own words are the message.
 */
test('the contact messages index renders for an admin', function () {
    $admin = Admin::factory()->create();
    ContactMessage::factory()->create(['subject' => 'Egyptian Mau missing']);

    $this->actingAs($admin, 'admin')
        ->getJson('/nova-api/contact-messages')
        ->assertOk()
        ->assertJsonCount(1, 'resources')
        ->assertJsonPath('resources.0.title', 'Egyptian Mau missing');
});

test('a contact message can be deleted but not edited', function () {
    $admin   = Admin::factory()->create();
    $message = ContactMessage::factory()->create();

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/contact-messages/{$message->getKey()}", ['subject' => 'Rewritten'])
        ->assertForbidden();

    $this->actingAs($admin, 'admin')
        ->deleteJson('/nova-api/contact-messages', ['resources' => [$message->getKey()]])
        ->assertOk();

    $this->assertModelMissing($message);
});
