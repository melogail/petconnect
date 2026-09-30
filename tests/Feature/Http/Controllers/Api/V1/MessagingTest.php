<?php

use App\Models\Conversation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('conversations', function () {
    test('starting one returns it with the peer', function () {
        $me = User::factory()->create();
        $peer = User::factory()->create();
        Sanctum::actingAs($me);

        $this->postJson(route('api.v1.conversations.store'), [
            'recipient_id' => $peer->getKey(),
            'initial_message' => 'Hi, is Luna still available?',
        ])
            ->assertCreated()
            ->assertJsonPath('peer.id', $peer->getKey())
            ->assertJsonPath('can_send', true);

        expect(Conversation::query()->count())->toBe(1);
    });

    test('the inbox is paginated and marks unread threads', function () {
        $me = User::factory()->create();
        $peer = User::factory()->create();
        Sanctum::actingAs($peer);
        $conversation = $this->postJson(route('api.v1.conversations.store'), [
            'recipient_id' => $me->getKey(),
            'initial_message' => 'Hello',
        ])->json('id');

        Sanctum::actingAs($me);

        $this->getJson(route('api.v1.conversations.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation)
            ->assertJsonPath('data.0.unread', true)
            ->assertJsonPath('data.0.last_message.content', 'Hello');

        $this->postJson(route('api.v1.conversations.read', $conversation))->assertNoContent();

        $this->getJson(route('api.v1.conversations.index'))->assertJsonPath('data.0.unread', false);
    });

    test('a non-participant cannot read a thread', function () {
        $conversation = Conversation::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.conversations.show', $conversation))->assertForbidden();
    });
});

describe('messages', function () {
    test('sending returns the message and the thread pages newest first', function () {
        $me = User::factory()->create();
        $peer = User::factory()->create();
        Sanctum::actingAs($me);
        $conversation = $this->postJson(route('api.v1.conversations.store'), [
            'recipient_id' => $peer->getKey(),
            'initial_message' => 'First',
        ])->json('id');

        $this->postJson(route('api.v1.conversations.messages.store', $conversation), ['content' => 'Second'])
            ->assertCreated()
            ->assertJsonPath('content', 'Second')
            ->assertJsonPath('is_mine', true)
            ->assertJsonPath('sender.id', $me->getKey());

        $this->getJson(route('api.v1.conversations.messages.index', $conversation))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.content', 'Second')
            ->assertJsonPath('data.1.content', 'First');
    });

    test('requires a verified account', function () {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->getJson(route('api.v1.conversations.index'))->assertForbidden();
    });
});
