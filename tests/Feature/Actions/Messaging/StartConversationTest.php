<?php

use App\Actions\Messaging\StartConversation;
use App\Enums\ConversationType;
use App\Exceptions\Messaging\CannotMessageSelf;
use App\Exceptions\Messaging\ConversationNotPermitted;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Pipelines\Messages\StartDirectConversation\EnsureRecipientAccepts;
use App\Pipelines\Messages\StartDirectConversation\StartDirectConversationContext;
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

/**
 * The existence oracle this flow used to be.
 *
 * A deactivated account's row is untouched by deactivation, so it survived
 * every existence check and the flow ran on to EnsureRecipientAccepts and
 * aborted there — a different exception, and a different HTTP status, from the
 * one an id that was never issued produced. Telling the two apart is telling a
 * stranger which ids exist. `StartConversation::resolveRecipient()` now asks
 * `User::resolveRouteBinding()`, which refuses a deactivated account, so both
 * cases abort on the same line with the same answer: "not addressable by id,
 * anywhere" (.ai/rules/app.md, .ai/rules/models.md).
 *
 * The equality is the pin, not the exception class on its own: accepting a
 * ModelNotFoundException for the deactivated case says nothing about whether
 * the two cases are still distinguishable. `getMessage()` is deliberately left
 * out of the comparison — it embeds the id, so it differs between the two for
 * reasons that have nothing to do with the oracle.
 */
test('refuses a deactivated recipient exactly as it refuses an id that was never issued', function () {
    $initiator = User::factory()->create();
    $deactivated = User::factory()->inactive()->create();

    $refusals = [];

    foreach ([$deactivated->getKey(), 9999] as $recipientId) {
        try {
            app(StartConversation::class)->handle($initiator, $recipientId);
            $refusals[] = ['exception' => null, 'model' => null];
        } catch (ModelNotFoundException $thrown) {
            $refusals[] = ['exception' => $thrown::class, 'model' => $thrown->getModel()];
        } catch (Throwable $thrown) {
            $refusals[] = ['exception' => $thrown::class, 'model' => null];
        }
    }

    [$forDeactivated, $forNeverIssued] = $refusals;

    expect($forDeactivated)->toBe(['exception' => ModelNotFoundException::class, 'model' => User::class])
        ->and($forNeverIssued)->toBe($forDeactivated);

    $this->assertDatabaseEmpty('conversations');
});

/**
 * Recipient consent, which the deactivated case above used to be the only
 * fixture for.
 *
 * It cannot be reached through the Action any more: `acceptsMessagesFrom()` is
 * `isActive()` today, and a recipient who fails that never gets past
 * resolution. So the step is exercised where it lives. The recipient here is
 * addressable — not deactivated — and simply refuses, which is the shape a
 * block list or per-recipient message setting will take when it lands in
 * `User::acceptsMessagesFrom()`; the step must keep aborting on it whatever
 * decides the answer.
 */
test('rejects a recipient who does not accept messages from the initiator and writes nothing', function () {
    $initiator = User::factory()->create();
    $refusing = Mockery::mock(User::class)->makePartial();
    $refusing->shouldReceive('acceptsMessagesFrom')->with($initiator)->andReturnFalse();

    $context = new StartDirectConversationContext(initiator: $initiator, recipient: $refusing);

    expect(fn () => (new EnsureRecipientAccepts)->handle($context, fn (): null => null))
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
