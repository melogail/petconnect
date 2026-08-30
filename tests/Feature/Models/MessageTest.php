<?php

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

test('rejects a mass assigned privileged column', function (string $column, mixed $value) {
    $message = Message::factory()->create();

    expect(fn () => $message->fill([$column => $value]))
        ->toThrow(MassAssignmentException::class);
})->with([
    'delivery status' => ['status', MessageStatus::Sent],
    'pinning moderator' => ['pinned_by', 1],
    'pin timestamp' => ['pinned_at', '2026-01-01 00:00:00'],
]);

test('sends a new message as unpinned with the default delivery status', function () {
    $conversation = Conversation::factory()->direct()->create();
    $sender = User::factory()->create();

    $message = Message::create([
        'conversation_id' => $conversation->getKey(),
        'sender_id' => $sender->getKey(),
        'content' => 'Is the puppy still available?',
        'type' => MessageType::Text,
    ]);

    $stored = $message->fresh();

    expect($stored->status)->toBe(MessageStatus::Sent)
        ->and($stored->pinned_by)->toBeNull()
        ->and($stored->pinned_at)->toBeNull()
        ->and($stored->is_pinned)->toBeFalse();
});
