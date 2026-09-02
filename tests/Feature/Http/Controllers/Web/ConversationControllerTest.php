<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/**
 * A direct thread the given user is one half of, with the other half returned
 * alongside it so a test can act as either side.
 *
 * @return array{0: Conversation, 1: User}
 */
function threadWithPeer(User $participant): array
{
    $peer = User::factory()->create();

    return [
        Conversation::factory()->direct()->withParticipants($participant, $peer)->create(),
        $peer,
    ];
}

describe('index', function () {
    test('redirects a guest to the login page', function () {
        $this->get(route('conversations.index'))->assertRedirect(route('login'));
    });

    test('redirects an unverified user to the verification notice', function () {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('conversations.index'))
            ->assertRedirect(route('verification.notice'));
    });

    test('lists the acting user own threads and none of anybody else', function () {
        $reader = User::factory()->create();
        [$mine] = threadWithPeer($reader);
        $theirs = Conversation::factory()->direct()
            ->withParticipants(User::factory()->create(), User::factory()->create())
            ->create();

        $this->actingAs($reader)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('messaging/Index')
                ->has('conversations.data', 1)
                ->where('conversations.data.0.id', $mine->getKey()));

        expect(Conversation::query()->whereKey($theirs->getKey())->exists())->toBeTrue();
    });

    /**
     * `peer` and `unread` are guarded on relationLoaded() and fall back to a
     * neutral value, so an inbox that stopped eager loading `users` would ship a
     * null peer and a permanently-read badge rather than throwing. The query
     * count cannot catch that — it would go *down* — so the payload has to.
     */
    test('names the other side of each thread and marks it unread until the cursor moves', function () {
        $reader = User::factory()->create();
        [$conversation, $peer] = threadWithPeer($reader);
        Message::factory()->for($conversation)->from($peer)->create(['content' => 'Is she still available?']);

        $this->actingAs($reader)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('conversations.data.0.peer.id', $peer->getKey())
                ->where('conversations.data.0.peer.username', $peer->username)
                ->where('conversations.data.0.last_message.content', 'Is she still available?')
                ->where('conversations.data.0.unread', true));

        $conversation->markAsReadFor($reader);

        $this->actingAs($reader)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('conversations.data.0.unread', false));
    });
});

describe('show', function () {
    test('renders the thread and its newest messages for a participant', function () {
        $reader = User::factory()->create();
        [$conversation, $peer] = threadWithPeer($reader);
        $message = Message::factory()->for($conversation)->from($peer)->create(['content' => 'Is she still available?']);

        $this->actingAs($reader)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('messaging/Show')
                ->where('conversation.id', $conversation->getKey())
                ->where('conversation.peer.id', $peer->getKey())
                ->where('messages.data.0.id', $message->getKey())
                ->where('messages.data.0.content', 'Is she still available?'));
    });

    /**
     * The composer cannot enforce a ceiling it has not been told: it hardcoded
     * 5000, which matched `petconnect.messaging.max_length` by coincidence and
     * would have drifted the moment either side moved. The bound is read
     * through the same MessageValidationRules accessor StoreMessageRequest's
     * `max:` rule is built from, so moving the config has to move both — which
     * is what the non-default value below checks and a hardcoded prop would
     * fail.
     */
    test('ships the length ceiling the composer draws its counter from', function () {
        config(['petconnect.messaging.max_length' => 140]);
        $reader = User::factory()->create();
        [$conversation] = threadWithPeer($reader);

        $this->actingAs($reader)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('messageBounds', ['max_length' => 140]));

        $this->actingAs($reader)
            ->from(route('conversations.show', $conversation))
            ->post(route('conversations.messages.store', $conversation), [
                'content' => str_repeat('a', 141),
            ])
            ->assertInvalid(['content']);
    });

    test('returns 403 for a user who is not in the conversation', function () {
        $conversation = Conversation::factory()->direct()
            ->withParticipants(User::factory()->create(), User::factory()->create())
            ->create();

        $this->actingAs(User::factory()->create())
            ->get(route('conversations.show', $conversation))
            ->assertForbidden();
    });

    test('returns 404 for a soft deleted conversation', function () {
        $reader = User::factory()->create();
        [$conversation] = threadWithPeer($reader);
        $conversation->delete();

        $this->actingAs($reader)
            ->get(route('conversations.show', $conversation))
            ->assertNotFound();
    });

    /**
     * Inertia v3 prefetches links and performs instant visits, so a GET that
     * moved the read cursor would clear the unread badge as the pointer crossed
     * an inbox row. The cursor moves on POST conversations.read and nowhere
     * else — see .ai/rules/controllers.md.
     */
    test('issues no write of any kind, so the read cursor stays where it was', function () {
        $reader = User::factory()->create();
        [$conversation, $peer] = threadWithPeer($reader);
        Message::factory()->count(2)->for($conversation)->from($peer)->create();

        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $this->actingAs($reader)->get(route('conversations.show', $conversation))->assertOk();

        $writes = array_values(array_filter(
            $statements,
            fn (string $sql): bool => ! Str::startsWith(Str::lower(ltrim($sql)), 'select')
        ));

        expect($writes)->toBe([])
            ->and($conversation->users()->whereKey($reader->getKey())->first()->pivot->last_read_at)->toBeNull();
    });
});

describe('store', function () {
    test('redirects a guest to the login page and opens nothing', function () {
        $recipient = User::factory()->create();

        $this->post(route('conversations.store'), ['recipient_id' => $recipient->getKey()])
            ->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('conversations');
    });

    test('redirects an unverified user to the verification notice and opens nothing', function () {
        $recipient = User::factory()->create();

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('conversations.store'), ['recipient_id' => $recipient->getKey()])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseEmpty('conversations');
    });

    test('opens a direct thread with both users in it and redirects onto it', function () {
        $initiator = User::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($initiator)
            ->post(route('conversations.store'), ['recipient_id' => $recipient->getKey()]);

        $conversation = Conversation::query()->sole();

        $response->assertRedirect(route('conversations.show', $conversation));
        expect($conversation->users->pluck('id')->all())
            ->toEqualCanonicalizing([$initiator->getKey(), $recipient->getKey()]);
        $this->assertDatabaseEmpty('messages');
    });

    test('sends the opening message when one is written', function () {
        $initiator = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($initiator)
            ->post(route('conversations.store'), [
                'recipient_id' => $recipient->getKey(),
                'initial_message' => 'Is she still available?',
            ])
            ->assertValid();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => Conversation::query()->sole()->getKey(),
            'sender_id' => $initiator->getKey(),
            'content' => 'Is she still available?',
        ]);
    });

    test('rejects a conversation with yourself and opens nothing', function () {
        $initiator = User::factory()->create();

        $this->actingAs($initiator)
            ->post(route('conversations.store'), ['recipient_id' => $initiator->getKey()])
            ->assertInvalid(['recipient_id' => 'You cannot start a conversation with yourself.']);

        $this->assertDatabaseEmpty('conversations');
    });

    test('rejects a recipient who does not exist and opens nothing', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('conversations.store'), ['recipient_id' => 9999])
            ->assertNotFound();

        $this->assertDatabaseEmpty('conversations');
    });

    /**
     * The existence oracle, pinned at the boundary a caller can actually reach.
     *
     * `Rule::exists('users', 'id')` reads a column deactivation does not touch,
     * so it passed a deactivated recipient through to the Action (404) and
     * refused an id that was never issued in the validator (422). Two statuses
     * is one bit per guess, and the ids are sequential: an unprivileged caller
     * could enumerate which accounts exist. Resolution answers both cases now,
     * identically, which is what `profile.show` already does — "not addressable
     * by id, anywhere" (.ai/rules/app.md).
     *
     * Both branches belong in one test because neither status means anything on
     * its own; it is their being the same that closes the oracle. Do not relax
     * the 9999 branch back to `assertInvalid()` — that records the leak as the
     * contract.
     */
    test('answers a deactivated recipient exactly as it answers an id that was never issued', function () {
        $deactivated = User::factory()->inactive()->create();
        $initiator = User::factory()->create();

        $this->actingAs($initiator)
            ->post(route('conversations.store'), ['recipient_id' => $deactivated->getKey()])
            ->assertNotFound();

        $this->actingAs($initiator)
            ->post(route('conversations.store'), ['recipient_id' => 9999])
            ->assertNotFound();

        $this->assertDatabaseEmpty('conversations');
    });

    test('rejects an opening message longer than the configured ceiling and opens nothing', function () {
        $recipient = User::factory()->create();
        $maxLength = config('petconnect.messaging.max_length');

        $this->actingAs(User::factory()->create())
            ->post(route('conversations.store'), [
                'recipient_id' => $recipient->getKey(),
                'initial_message' => Str::repeat('a', $maxLength + 1),
            ])
            ->assertInvalid(['initial_message' => 'must not be greater than '.$maxLength]);

        $this->assertDatabaseEmpty('conversations');
    });

    test('returns 429 once the acting user passes 5 new conversations in a minute', function () {
        $initiator = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($initiator)
                ->post(route('conversations.store'), ['recipient_id' => User::factory()->create()->getKey()])
                ->assertRedirect();
        }

        $this->actingAs($initiator)
            ->post(route('conversations.store'), ['recipient_id' => User::factory()->create()->getKey()])
            ->assertTooManyRequests();

        expect(Conversation::query()->count())->toBe(5);
    });
});

describe('read', function () {
    test('moves the acting participant read cursor', function () {
        $reader = User::factory()->create();
        [$conversation, $peer] = threadWithPeer($reader);
        Message::factory()->for($conversation)->from($peer)->create();

        $this->freezeTime();

        $this->actingAs($reader)
            ->from(route('conversations.show', $conversation))
            ->post(route('conversations.read', $conversation))
            ->assertRedirect(route('conversations.show', $conversation));

        expect($conversation->fresh()->isUnreadFor($reader))->toBeFalse();
        $this->assertDatabaseHas('conversation_user', [
            'conversation_id' => $conversation->getKey(),
            'user_id' => $reader->getKey(),
            'last_read_at' => now()->toDateTimeString(),
        ]);
    });

    test('returns 403 for a user who is not in the conversation and moves no cursor', function () {
        $conversation = Conversation::factory()->direct()
            ->withParticipants(User::factory()->create(), User::factory()->create())
            ->create();

        $this->actingAs(User::factory()->create())
            ->post(route('conversations.read', $conversation))
            ->assertForbidden();

        $this->assertDatabaseMissing('conversation_user', [
            'conversation_id' => $conversation->getKey(),
            'last_read_at' => now()->toDateTimeString(),
        ]);
    });

    test('returns 404 for a soft deleted conversation', function () {
        $reader = User::factory()->create();
        [$conversation] = threadWithPeer($reader);
        $conversation->delete();

        $this->actingAs($reader)
            ->post(route('conversations.read', $conversation))
            ->assertNotFound();
    });
});
