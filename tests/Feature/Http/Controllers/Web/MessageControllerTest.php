<?php

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * A direct thread and one message already in it, sent by the first user.
 *
 * Returned as a tuple so each test can act as whichever corner of the
 * participant/sender/outsider matrix it is about.
 *
 * @return array{0: Conversation, 1: Message}
 */
function threadWithMessageFrom(User $sender, User $peer, string $content = 'Original'): array
{
    $conversation = Conversation::factory()->direct()->withParticipants($sender, $peer)->create();

    return [
        $conversation,
        Message::factory()->for($conversation)->from($sender)->create(['content' => $content]),
    ];
}

describe('index', function () {
    test('redirects a guest to the login page', function () {
        $conversation = Conversation::factory()->direct()
            ->withParticipants(User::factory()->create(), User::factory()->create())
            ->create();

        $this->get(route('conversations.messages.index', $conversation))->assertRedirect(route('login'));
    });

    test('returns the newest page of the thread as JSON for a participant', function () {
        $reader = User::factory()->create();
        [, $message] = threadWithMessageFrom(User::factory()->create(), $reader, 'Is she still available?');

        $this->actingAs($reader)
            ->get(route('conversations.messages.index', $message->conversation))
            ->assertOk()
            ->assertJsonPath('data.0.id', $message->getKey())
            ->assertJsonPath('data.0.content', 'Is she still available?')
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    test('returns 403 for a user who is not in the conversation and leaks no message text', function () {
        [$conversation] = threadWithMessageFrom(
            User::factory()->create(),
            User::factory()->create(),
            'Ring me on the number in my bio.',
        );

        $this->actingAs(User::factory()->create())
            ->get(route('conversations.messages.index', $conversation))
            ->assertForbidden()
            ->assertDontSee('Ring me on the number in my bio.');
    });

    test('returns 404 for a soft deleted conversation', function () {
        $reader = User::factory()->create();
        [$conversation] = threadWithMessageFrom($reader, User::factory()->create());
        $conversation->delete();

        $this->actingAs($reader)
            ->get(route('conversations.messages.index', $conversation))
            ->assertNotFound();
    });
});

describe('store', function () {
    test('redirects a guest to the login page and writes nothing', function () {
        $conversation = Conversation::factory()->direct()
            ->withParticipants(User::factory()->create(), User::factory()->create())
            ->create();

        $this->post(route('conversations.messages.store', $conversation), ['content' => 'Hello'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('messages');
    });

    test('redirects an unverified user to the verification notice and writes nothing', function () {
        $sender = User::factory()->unverified()->create();
        $conversation = Conversation::factory()->direct()
            ->withParticipants($sender, User::factory()->create())
            ->create();

        $this->actingAs($sender)
            ->post(route('conversations.messages.store', $conversation), ['content' => 'Hello'])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseEmpty('messages');
    });

    test('writes the message and notifies the other side', function () {
        Notification::fake();
        $sender = User::factory()->create();
        $peer = User::factory()->create();
        $conversation = Conversation::factory()->direct()->withParticipants($sender, $peer)->create();

        $this->actingAs($sender)
            ->from(route('conversations.show', $conversation))
            ->post(route('conversations.messages.store', $conversation), ['content' => 'Is she still available?'])
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $sender->getKey(),
            'content' => 'Is she still available?',
            'type' => MessageType::Text->value,
        ]);
        Notification::assertSentTo($peer, NewMessageNotification::class);
        Notification::assertNotSentTo($sender, NewMessageNotification::class);
    });

    test('stamps the sender from the session rather than from the payload', function () {
        $sender = User::factory()->create();
        $peer = User::factory()->create();
        $conversation = Conversation::factory()->direct()->withParticipants($sender, $peer)->create();

        $this->actingAs($sender)
            ->from(route('conversations.show', $conversation))
            ->post(route('conversations.messages.store', $conversation), [
                'content' => 'Is she still available?',
                'sender_id' => $peer->getKey(),
                'conversation_id' => 9999,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'sender_id' => $sender->getKey(),
            'conversation_id' => $conversation->getKey(),
        ]);
        $this->assertDatabaseMissing('messages', ['sender_id' => $peer->getKey()]);
    });

    test('cleans the submitted text before storing it', function () {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->direct()
            ->withParticipants($sender, User::factory()->create())
            ->create();

        $this->actingAs($sender)
            ->from(route('conversations.show', $conversation))
            ->post(route('conversations.messages.store', $conversation), ['content' => '  What a   bitch  '])
            ->assertValid();

        expect(Message::query()->sole()->content)->toBe('What a ****');
    });

    test('returns 403 for a user who is not in the conversation and writes nothing', function () {
        $conversation = Conversation::factory()->direct()
            ->withParticipants(User::factory()->create(), User::factory()->create())
            ->create();

        $this->actingAs(User::factory()->create())
            ->post(route('conversations.messages.store', $conversation), ['content' => 'Let me in'])
            ->assertForbidden();

        $this->assertDatabaseEmpty('messages');
    });

    /**
     * The thread was opened while both sides were active, so nothing on the
     * create path ever asked this question — MessagePolicy::create is the only
     * thing that asks it again on every subsequent message.
     */
    test('returns 403 when the recipient has deactivated since the thread was opened, and writes nothing', function () {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->direct()
            ->withParticipants($sender, User::factory()->inactive()->create())
            ->create();
        Notification::fake();

        $this->actingAs($sender)
            ->post(route('conversations.messages.store', $conversation), ['content' => 'Are you still there?'])
            ->assertForbidden();

        $this->assertDatabaseEmpty('messages');
        Notification::assertNothingSent();
    });

    test('returns 404 for a soft deleted conversation and writes nothing', function () {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->direct()
            ->withParticipants($sender, User::factory()->create())
            ->create();
        $conversation->delete();

        $this->actingAs($sender)
            ->post(route('conversations.messages.store', $conversation), ['content' => 'Hello'])
            ->assertNotFound();

        $this->assertDatabaseEmpty('messages');
    });

    test('rejects a message with no content and writes nothing', function () {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->direct()
            ->withParticipants($sender, User::factory()->create())
            ->create();

        $this->actingAs($sender)
            ->post(route('conversations.messages.store', $conversation), ['content' => ''])
            ->assertInvalid(['content' => 'The content field is required.']);

        $this->assertDatabaseEmpty('messages');
    });

    test('rejects a message longer than the configured ceiling and writes nothing', function () {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->direct()
            ->withParticipants($sender, User::factory()->create())
            ->create();
        $maxLength = config('petconnect.messaging.max_length');

        $this->actingAs($sender)
            ->post(route('conversations.messages.store', $conversation), [
                'content' => Str::repeat('a', $maxLength + 1),
            ])
            ->assertInvalid(['content' => 'must not be greater than '.$maxLength]);

        $this->assertDatabaseEmpty('messages');
    });

    test('rejects a payload type that is not a MessageType case and writes nothing', function () {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->direct()
            ->withParticipants($sender, User::factory()->create())
            ->create();

        $this->actingAs($sender)
            ->post(route('conversations.messages.store', $conversation), [
                'content' => 'Hello',
                'type' => 'telegram',
            ])
            ->assertInvalid(['type']);

        $this->assertDatabaseEmpty('messages');
    });

    test('returns 429 once the acting user passes 30 messages in a minute', function () {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->direct()
            ->withParticipants($sender, User::factory()->create())
            ->create();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->actingAs($sender)
                ->post(route('conversations.messages.store', $conversation), ['content' => 'Message '.$attempt])
                ->assertRedirect();
        }

        $this->actingAs($sender)
            ->post(route('conversations.messages.store', $conversation), ['content' => 'One too many'])
            ->assertTooManyRequests();

        $this->assertDatabaseMissing('messages', ['content' => 'One too many']);
    });
});

describe('update', function () {
    test('redirects a guest to the login page and leaves the text unchanged', function () {
        [, $message] = threadWithMessageFrom(User::factory()->create(), User::factory()->create());

        $this->put(route('messages.update', $message), ['content' => 'Edited'])
            ->assertRedirect(route('login'));

        expect($message->fresh()->content)->toBe('Original');
    });

    test('applies the edit for the sender', function () {
        $sender = User::factory()->create();
        [$conversation, $message] = threadWithMessageFrom($sender, User::factory()->create());

        $this->actingAs($sender)
            ->from(route('conversations.show', $conversation))
            ->put(route('messages.update', $message), ['content' => 'Edited'])
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseHas('messages', [
            'id' => $message->getKey(),
            'content' => 'Edited',
        ]);
    });

    /**
     * `is_edited` is read off `messages.edited_at`, not derived from
     * `updated_at`, so the column is the contract and the one the pin path must
     * leave alone. See .ai/rules/resources.md.
     */
    test('stamps the edit timestamp on the revised message', function () {
        $this->freezeTime();
        $sender = User::factory()->create();
        [$conversation, $message] = threadWithMessageFrom($sender, User::factory()->create());

        $this->actingAs($sender)
            ->from(route('conversations.show', $conversation))
            ->put(route('messages.update', $message), ['content' => 'Edited'])
            ->assertValid();

        $this->assertDatabaseHas('messages', [
            'id' => $message->getKey(),
            'edited_at' => now()->toDateTimeString(),
        ]);
        expect($message->fresh()->is_edited)->toBeTrue();
    });

    test('cleans the edited text, so a message cannot be edited around the filter', function () {
        $sender = User::factory()->create();
        [$conversation, $message] = threadWithMessageFrom($sender, User::factory()->create());

        $this->actingAs($sender)
            ->from(route('conversations.show', $conversation))
            ->put(route('messages.update', $message), ['content' => '  What a   bitch  '])
            ->assertValid();

        expect($message->fresh()->content)->toBe('What a ****');
    });

    test('returns 403 for the other participant and leaves the text unchanged', function () {
        $peer = User::factory()->create();
        [, $message] = threadWithMessageFrom(User::factory()->create(), $peer);

        $this->actingAs($peer)
            ->put(route('messages.update', $message), ['content' => 'Edited'])
            ->assertForbidden();

        expect($message->fresh()->content)->toBe('Original');
    });

    test('returns 403 for a user who is not in the conversation and leaves the text unchanged', function () {
        [, $message] = threadWithMessageFrom(User::factory()->create(), User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->put(route('messages.update', $message), ['content' => 'Edited'])
            ->assertForbidden();

        expect($message->fresh()->content)->toBe('Original');
    });

    test('returns 403 once the edit window has closed and leaves the text unchanged', function () {
        config(['petconnect.messaging.edit_window_minutes' => 15]);
        $sender = User::factory()->create();
        [, $message] = threadWithMessageFrom($sender, User::factory()->create());
        $message->forceFill(['created_at' => now()->subMinutes(16)])->saveQuietly();

        $this->actingAs($sender)
            ->put(route('messages.update', $message), ['content' => 'Edited'])
            ->assertForbidden();

        expect($message->fresh()->content)->toBe('Original');
    });

    test('rejects an edit with no content and leaves the text unchanged', function () {
        $sender = User::factory()->create();
        [, $message] = threadWithMessageFrom($sender, User::factory()->create());

        $this->actingAs($sender)
            ->put(route('messages.update', $message), ['content' => ''])
            ->assertInvalid(['content' => 'The content field is required.']);

        expect($message->fresh()->content)->toBe('Original');
    });
});

describe('destroy', function () {
    test('withdraws the message for its sender', function () {
        $sender = User::factory()->create();
        [$conversation, $message] = threadWithMessageFrom($sender, User::factory()->create());

        $this->actingAs($sender)
            ->from(route('conversations.show', $conversation))
            ->delete(route('messages.destroy', $message))
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertSoftDeleted($message);
    });

    test('returns 403 for the other participant and leaves the message in place', function () {
        $peer = User::factory()->create();
        [, $message] = threadWithMessageFrom(User::factory()->create(), $peer);

        $this->actingAs($peer)->delete(route('messages.destroy', $message))->assertForbidden();

        $this->assertNotSoftDeleted($message);
    });

    test('returns 403 for a user who is not in the conversation and leaves the message in place', function () {
        [, $message] = threadWithMessageFrom(User::factory()->create(), User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->delete(route('messages.destroy', $message))
            ->assertForbidden();

        $this->assertNotSoftDeleted($message);
    });

    test('withdraws a message the edit window has long closed on', function () {
        config(['petconnect.messaging.edit_window_minutes' => 15]);
        $sender = User::factory()->create();
        [, $message] = threadWithMessageFrom($sender, User::factory()->create());
        $message->forceFill(['created_at' => now()->subYear()])->saveQuietly();

        $this->actingAs($sender)
            ->from(route('conversations.index'))
            ->delete(route('messages.destroy', $message))
            ->assertRedirect();

        $this->assertSoftDeleted($message);
    });
});

describe('pin', function () {
    test('pins a message for its sender and records who pinned it', function () {
        $sender = User::factory()->create();
        [$conversation, $message] = threadWithMessageFrom($sender, User::factory()->create());

        $this->actingAs($sender)
            ->from(route('conversations.show', $conversation))
            ->post(route('messages.pin', $message))
            ->assertRedirect(route('conversations.show', $conversation));

        expect($message->fresh())
            ->is_pinned->toBeTrue()
            ->pinned_by->toBe($sender->getKey());
    });

    test('lets the other participant pin a message they did not send', function () {
        $peer = User::factory()->create();
        [, $message] = threadWithMessageFrom(User::factory()->create(), $peer);

        $this->actingAs($peer)
            ->from(route('conversations.index'))
            ->post(route('messages.pin', $message))
            ->assertRedirect();

        expect($message->fresh()->pinned_by)->toBe($peer->getKey());
    });

    test('unpins a message that is already pinned and clears the attribution', function () {
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->direct()
            ->withParticipants($sender, User::factory()->create())
            ->create();
        $message = Message::factory()->for($conversation)->from($sender)->pinned()->create();

        $this->actingAs($sender)
            ->from(route('conversations.index'))
            ->post(route('messages.pin', $message))
            ->assertRedirect();

        expect($message->fresh())
            ->is_pinned->toBeFalse()
            ->pinned_at->toBeNull()
            ->pinned_by->toBeNull();
    });

    test('returns 403 for a user who is not in the conversation and pins nothing', function () {
        [, $message] = threadWithMessageFrom(User::factory()->create(), User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('messages.pin', $message))
            ->assertForbidden();

        expect($message->fresh()->is_pinned)->toBeFalse();
    });

    /**
     * Pinning is a bookmark, not a revision. `is_edited` must stay false for a
     * message whose words nobody has touched — otherwise pinning an hour-old
     * message tells the recipient it was rewritten.
     */
    test('does not mark an hour old message as edited', function () {
        $sender = User::factory()->create();
        [, $message] = threadWithMessageFrom($sender, User::factory()->create());
        $message->forceFill([
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ])->saveQuietly();

        $this->actingAs($sender)
            ->from(route('conversations.index'))
            ->post(route('messages.pin', $message))
            ->assertRedirect();

        expect($message->fresh())
            ->is_pinned->toBeTrue()
            ->edited_at->toBeNull();

        $this->actingAs($sender)
            ->get(route('conversations.messages.index', $message->conversation))
            ->assertOk()
            ->assertJsonPath('data.0.is_edited', false);
    });

    test('marks a message as edited once its text is actually revised', function () {
        $sender = User::factory()->create();
        [$conversation, $message] = threadWithMessageFrom($sender, User::factory()->create());

        $this->actingAs($sender)
            ->from(route('conversations.show', $conversation))
            ->put(route('messages.update', $message), ['content' => 'Edited'])
            ->assertValid();

        $this->actingAs($sender)
            ->get(route('conversations.messages.index', $conversation))
            ->assertOk()
            ->assertJsonPath('data.0.is_edited', true);
    });
});

/**
 * The whole `{message}` binding surface, grouped by the binding rather than by
 * controller action because one rule decides all three routes: Message's route
 * binding refuses to bind a message whose conversation is soft-deleted, so a
 * retired thread's messages 404 from every route that addresses a message by
 * its id and never names the conversation.
 *
 * Each 404 is paired with the live-conversation case, so a failure separates
 * "the trashed conversation is hidden" from "the route is broken for everyone".
 *
 * Every case acts as the message's own sender: Authenticate sorts ahead of
 * SubstituteBindings, so a guest is redirected to the login page before the
 * binding is ever resolved and could never have seen the leak.
 */
describe('route binding', function () {
    test('returns 404 from the update of a message in a soft deleted conversation, and leaves the text unchanged', function () {
        $sender = User::factory()->create();
        [$conversation, $message] = threadWithMessageFrom($sender, User::factory()->create());
        $conversation->delete();

        $this->actingAs($sender)
            ->put(route('messages.update', $message), ['content' => 'Edited'])
            ->assertNotFound();

        expect($message->fresh()->content)->toBe('Original');
    });

    test('returns 404 from the destroy of a message in a soft deleted conversation, and leaves it in place', function () {
        $sender = User::factory()->create();
        [$conversation, $message] = threadWithMessageFrom($sender, User::factory()->create());
        $conversation->delete();

        $this->actingAs($sender)
            ->delete(route('messages.destroy', $message))
            ->assertNotFound();

        $this->assertNotSoftDeleted($message);
    });

    test('returns 404 from the pin of a message in a soft deleted conversation, and pins nothing', function () {
        $sender = User::factory()->create();
        [$conversation, $message] = threadWithMessageFrom($sender, User::factory()->create());
        $conversation->delete();

        $this->actingAs($sender)
            ->post(route('messages.pin', $message))
            ->assertNotFound();

        expect($message->fresh()->is_pinned)->toBeFalse();
    });

    test('serves a message in a live conversation', function (string $method, string $routeName, array $payload) {
        $sender = User::factory()->create();
        [, $message] = threadWithMessageFrom($sender, User::factory()->create());

        $this->actingAs($sender)
            ->from(route('conversations.index'))
            ->call($method, route($routeName, $message), $payload)
            ->assertRedirect();
    })->with([
        'update' => ['put', 'messages.update', ['content' => 'Edited']],
        'destroy' => ['delete', 'messages.destroy', []],
        'pin' => ['post', 'messages.pin', []],
    ]);

    test('returns 404 for a message id that does not exist', function (string $method, string $routeName, array $payload) {
        $this->actingAs(User::factory()->create())
            ->call($method, route($routeName, 9999), $payload)
            ->assertNotFound();
    })->with([
        'update' => ['put', 'messages.update', ['content' => 'Edited']],
        'destroy' => ['delete', 'messages.destroy', []],
        'pin' => ['post', 'messages.pin', []],
    ]);
});
