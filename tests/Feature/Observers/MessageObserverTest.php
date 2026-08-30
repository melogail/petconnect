<?php

use App\Models\Conversation;
use App\Models\Message;

test('sets the conversation cursor to the created message time', function () {
    $conversation = Conversation::factory()->direct()->create();

    $message = Message::factory()->for($conversation)->create(['created_at' => '2026-01-01 10:00:00']);

    expect($conversation->fresh()->last_message_at->toDateTimeString())
        ->toBe($message->created_at->toDateTimeString());
});

test('falls back to the next newest message when the newest is deleted', function () {
    $conversation = Conversation::factory()->direct()->create();
    $older = Message::factory()->for($conversation)->create(['created_at' => '2026-01-01 10:00:00']);
    $newest = Message::factory()->for($conversation)->create(['created_at' => '2026-01-02 10:00:00']);

    $newest->delete();

    expect($conversation->fresh()->last_message_at->toDateTimeString())
        ->toBe($older->created_at->toDateTimeString());
});

test('clears the cursor when the last remaining message is deleted', function () {
    $conversation = Conversation::factory()->direct()->create();
    $message = Message::factory()->for($conversation)->create(['created_at' => '2026-01-01 10:00:00']);

    $message->delete();

    expect($conversation->fresh()->last_message_at)->toBeNull();
});

test('leaves the cursor alone when an older message is deleted', function () {
    $conversation = Conversation::factory()->direct()->create();
    Message::factory()->for($conversation)->create(['created_at' => '2026-01-01 10:00:00']);
    $newest = Message::factory()->for($conversation)->create(['created_at' => '2026-01-02 10:00:00']);

    $conversation->messages()->oldest('created_at')->first()->delete();

    expect($conversation->fresh()->last_message_at->toDateTimeString())
        ->toBe($newest->created_at->toDateTimeString());
});

test('restores the cursor when the newest message is restored', function () {
    $conversation = Conversation::factory()->direct()->create();
    Message::factory()->for($conversation)->create(['created_at' => '2026-01-01 10:00:00']);
    $newest = Message::factory()->for($conversation)->create(['created_at' => '2026-01-02 10:00:00']);
    $newest->delete();

    $newest->restore();

    expect($conversation->fresh()->last_message_at->toDateTimeString())
        ->toBe($newest->created_at->toDateTimeString());
});

test('recomputes the cursor when the newest message is force deleted', function () {
    $conversation = Conversation::factory()->direct()->create();
    $older = Message::factory()->for($conversation)->create(['created_at' => '2026-01-01 10:00:00']);
    $newest = Message::factory()->for($conversation)->create(['created_at' => '2026-01-02 10:00:00']);

    $newest->forceDelete();

    expect($conversation->fresh()->last_message_at->toDateTimeString())
        ->toBe($older->created_at->toDateTimeString());
});

test('clears the cursor when the only message is force deleted', function () {
    $conversation = Conversation::factory()->direct()->create();
    $message = Message::factory()->for($conversation)->create(['created_at' => '2026-01-01 10:00:00']);

    $message->forceDelete();

    expect($conversation->fresh()->last_message_at)->toBeNull();
});
