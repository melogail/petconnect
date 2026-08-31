<?php

use App\Actions\Messaging\SendMessage;
use App\Exceptions\Messaging\NotAConversationParticipant;
use App\Exceptions\Messaging\RecipientNotAcceptingMessages;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Facades\Notification;

/**
 * A direct thread between the two given users, already open.
 */
function openThreadBetween(User $first, User $second): Conversation
{
    return Conversation::factory()->direct()->withParticipants($first, $second)->create();
}

/**
 * The consent seam the start-conversation flow cannot hold.
 *
 * `conversations.store` asks whether the recipient accepts a stranger exactly
 * once, when the thread is opened. Every message after that is posted to a
 * thread that already exists, so a peer who deactivates afterwards is only
 * protected by this step — see App\Exceptions\Messaging\
 * RecipientNotAcceptingMessages.
 */
test('refuses to send into an open thread whose recipient has since deactivated, and writes nothing', function () {
    $sender = User::factory()->create();
    $conversation = openThreadBetween($sender, User::factory()->inactive()->create());
    Notification::fake();

    expect(fn () => app(SendMessage::class)->handle($conversation, $sender, 'Are you still there?'))
        ->toThrow(RecipientNotAcceptingMessages::class);

    $this->assertDatabaseEmpty('messages');
    Notification::assertNothingSent();
});

/**
 * The pair to the case above: without it, a guard that refused every send would
 * pass just as happily.
 */
test('sends into the same thread when the recipient still accepts messages', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = openThreadBetween($sender, $recipient);
    Notification::fake();

    $message = app(SendMessage::class)->handle($conversation, $sender, 'Are you still there?');

    $this->assertDatabaseHas('messages', [
        'id' => $message->getKey(),
        'conversation_id' => $conversation->getKey(),
        'sender_id' => $sender->getKey(),
        'content' => 'Are you still there?',
    ]);
    Notification::assertSentTo($recipient, NewMessageNotification::class);
});

/**
 * MessagePolicy::create refuses this over HTTP, so the step only speaks for the
 * callers that pass no policy — a seeder, a console command, another Action.
 */
test('refuses to send into a thread the sender is not part of, and writes nothing', function () {
    $conversation = openThreadBetween(User::factory()->create(), User::factory()->create());
    $outsider = User::factory()->create();
    Notification::fake();

    expect(fn () => app(SendMessage::class)->handle($conversation, $outsider, 'Let me in'))
        ->toThrow(NotAConversationParticipant::class);

    $this->assertDatabaseEmpty('messages');
    Notification::assertNothingSent();
});
