<?php

use App\Models\Conversation;
use App\Models\User;

test('reading the inbox is allowed for any signed in user', function () {
    expect(User::factory()->create()->can('viewAny', Conversation::class))->toBeTrue();
});

test('a participant may read their own thread', function () {
    $participant = User::factory()->create();
    $conversation = Conversation::factory()
        ->direct()
        ->withParticipants($participant, User::factory()->create())
        ->create();

    expect($participant->can('view', $conversation))->toBeTrue();
});

test('a user who is not in the conversation may not read it', function () {
    $conversation = Conversation::factory()
        ->direct()
        ->withParticipants(User::factory()->create(), User::factory()->create())
        ->create();

    expect(User::factory()->create()->can('view', $conversation))->toBeFalse();
});

test('opening a conversation is allowed for a verified user and refused for an unverified one', function () {
    $verified = User::factory()->create();
    $unverified = User::factory()->unverified()->create();

    expect($verified->can('create', Conversation::class))->toBeTrue()
        ->and($unverified->can('create', Conversation::class))->toBeFalse();
});
