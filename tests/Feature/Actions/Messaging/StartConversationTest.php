<?php

use App\Actions\Messaging\StartConversation;
use App\Enums\ConversationType;
use App\Exceptions\Messaging\CannotMessageSelf;
use App\Exceptions\Messaging\ConversationNotPermitted;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;

test('opens a direct thread holding exactly the two users', function () {
    $initiator = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = app(StartConversation::class)->handle($initiator, $recipient->getKey());

    expect($conversation->type)->toBe(ConversationType::Direct)
        ->and($conversation->users->pluck('id')->all())
        ->toEqualCanonicalizing([$initiator->getKey(), $recipient->getKey()]);
});

/**
 * Pressing "Message" twice has to land on the same thread. `conversations`
 * carries no unique index over a participant pair — the pair lives in
 * `conversation_user`, whose unique index is per (conversation, user) and
 * cannot express "these two, once" — so idempotency is the flow's job.
 */
test('reopens the same thread when the same initiator asks a second time', function () {
    $initiator = User::factory()->create();
    $recipient = User::factory()->create();

    $first = app(StartConversation::class)->handle($initiator, $recipient->getKey());
    $second = app(StartConversation::class)->handle($initiator, $recipient->getKey());

    expect($second->getKey())->toBe($first->getKey())
        ->and(Conversation::query()->count())->toBe(1);
});

test('reopens the same thread when the other side asks from their end', function () {
    $initiator = User::factory()->create();
    $recipient = User::factory()->create();

    $opened = app(StartConversation::class)->handle($initiator, $recipient->getKey());
    $reopened = app(StartConversation::class)->handle($recipient, $initiator->getKey());

    expect($reopened->getKey())->toBe($opened->getKey())
        ->and(Conversation::query()->count())->toBe(1)
        ->and($opened->users()->count())->toBe(2);
});

test('adds a later opening message to the thread that already exists rather than forking it', function () {
    $initiator = User::factory()->create();
    $recipient = User::factory()->create();
    $opened = app(StartConversation::class)->handle($initiator, $recipient->getKey(), 'Is she still available?');

    $reopened = app(StartConversation::class)->handle($recipient, $initiator->getKey(), 'She is, message me.');

    expect($reopened->getKey())->toBe($opened->getKey())
        ->and(Conversation::query()->count())->toBe(1)
        ->and($opened->messages()->pluck('content')->all())
        ->toEqualCanonicalizing(['Is she still available?', 'She is, message me.']);
});

test('rejects opening a thread with yourself and writes nothing', function () {
    $initiator = User::factory()->create();

    expect(fn () => app(StartConversation::class)->handle($initiator, $initiator->getKey()))
        ->toThrow(CannotMessageSelf::class);

    $this->assertDatabaseEmpty('conversations');
});

test('rejects a deactivated recipient and writes nothing', function () {
    $recipient = User::factory()->inactive()->create();

    expect(fn () => app(StartConversation::class)->handle(User::factory()->create(), $recipient->getKey()))
        ->toThrow(ConversationNotPermitted::class);

    $this->assertDatabaseEmpty('conversations');
});

test('rejects a recipient who no longer exists and writes nothing', function () {
    expect(fn () => app(StartConversation::class)->handle(User::factory()->create(), 9999))
        ->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseEmpty('conversations');
});

test('opens a thread with no message when none was written', function () {
    $conversation = app(StartConversation::class)
        ->handle(User::factory()->create(), User::factory()->create()->getKey());

    expect($conversation->messages()->count())->toBe(0)
        ->and($conversation->fresh()->last_message_at)->toBeNull();
});

test('sends the opening message through the same flow every later message takes', function () {
    Notification::fake();
    $initiator = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = app(StartConversation::class)
        ->handle($initiator, $recipient->getKey(), '  What a   bitch  ');

    $message = Message::query()->sole();

    expect($message->content)->toBe('What a ****')
        ->and($message->sender_id)->toBe($initiator->getKey())
        ->and($message->conversation_id)->toBe($conversation->getKey())
        ->and($conversation->fresh()->last_message_at)->not->toBeNull();

    Notification::assertSentTo($recipient, NewMessageNotification::class);
    Notification::assertNotSentTo($initiator, NewMessageNotification::class);
});

/**
 * The initiator is redirected straight onto the thread they just opened, so it
 * must not appear unread to them — including when the flow merely reopened a
 * thread already holding messages from the other side.
 */
test('leaves the thread read for the initiator', function () {
    $initiator = User::factory()->create();
    $recipient = User::factory()->create();
    $opened = app(StartConversation::class)->handle($recipient, $initiator->getKey(), 'Is she still available?');

    expect($opened->fresh()->isUnreadFor($initiator))->toBeTrue();

    $reopened = app(StartConversation::class)->handle($initiator, $recipient->getKey());

    expect($reopened->fresh()->isUnreadFor($initiator))->toBeFalse();
});
