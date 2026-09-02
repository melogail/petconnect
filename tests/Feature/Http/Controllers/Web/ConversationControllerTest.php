<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Middleware;
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

/**
 * Open a thread with a recipient the caller is not allowed to address, and
 * record **everything** that caller can observe about the refusal.
 *
 * The point is a comparison, so the record has to be wider than the status: two
 * refusals that agree on 404 and disagree on their body, their redirect, their
 * headers, their cookies or what they left in the session are still one bit of
 * information about which ids exist. What is captured is therefore the whole
 * response — status, every header, the rendered body — plus the session the
 * request left behind, which is where a validation error or a flash message
 * would land on a redirect rather than in the body.
 *
 * `session_id_changed` is here because the session cookie's value has to be
 * normalised away (see `withoutPerResponseNoise()`) and a branch that
 * invalidated or regenerated the session would otherwise hide inside that
 * normalisation. The id itself is not comparable — the two calls share one
 * session, so a regeneration in the first would carry into the second and read
 * as agreement — but "did this request change it" is.
 *
 * Each call takes a fresh initiator on purpose. `conversations` is throttled per
 * user (AppServiceProvider::configureRateLimiters), so reusing one caller would
 * make `x-ratelimit-remaining` count down between the two requests and force a
 * normalisation of a header that has nothing to do with the oracle. A separate
 * initiator leaves both requests first in their own bucket and the header
 * asserted at its real value.
 *
 * @param  bool  $asInertiaVisit  Send the headers the real client sends. Inertia
 *                                takes its own path through the response, and a
 *                                404 on it is not the 404 a document request
 *                                gets — .ai/rules/messaging.md.
 * @return array{response: TestResponse, observed: array{status: int, headers: array<string, list<string|null>>, content: string, session: array<string, mixed>, session_id_changed: bool}}
 */
function refusalToOpenThreadWith(int $recipientId, bool $asInertiaVisit = false): array
{
    $sessionIdBefore = session()->getId();

    $caller = test()->actingAs(User::factory()->create());

    if ($asInertiaVisit) {
        $caller = $caller->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(Middleware::class)->version(request()),
        ]);
    }

    $response = $caller->post(route('conversations.store'), ['recipient_id' => $recipientId]);

    return [
        'response' => $response,
        'observed' => [
            'status' => $response->getStatusCode(),
            'headers' => withoutPerResponseNoise($response->headers->all()),
            'content' => (string) $response->getContent(),
            'session' => session()->all(),
            'session_id_changed' => session()->getId() !== $sessionIdBefore,
        ],
    ];
}

/**
 * Blank the three header values that cannot repeat between two responses, and
 * nothing else.
 *
 * - `date` is stamped from the wall clock by Symfony's Response constructor —
 *   `time()`, not Carbon, so freezing time does not reach it — and two requests
 *   in one test can straddle a second boundary.
 * - a `set-cookie` value is an AES payload with a random IV, so it differs even
 *   when the plaintext does not. Under `SESSION_DRIVER=array` that plaintext is
 *   an opaque session id and the CSRF token, and both are recorded elsewhere:
 *   the token is `_token` in the captured session, and a changed session id is
 *   `session_id_changed`. Only the value between `name=` and the first `;` is
 *   replaced, so the cookie's name and every attribute — `Max-Age`, `path`,
 *   `secure`, `httponly`, `samesite` — are still compared, as is the set of
 *   cookies itself.
 * - `expires` inside those cookies is an absolute timestamp derived from the
 *   same wall clock as `date`.
 *
 * Both header names survive the normalisation, so a branch that dropped a
 * header the other kept still fails the comparison.
 *
 * @param  array<string, list<string|null>>  $headers
 * @return array<string, list<string|null>>
 */
function withoutPerResponseNoise(array $headers): array
{
    if (isset($headers['date'])) {
        $headers['date'] = array_fill(0, count($headers['date']), '<stamped-at>');
    }

    if (isset($headers['set-cookie'])) {
        $headers['set-cookie'] = array_map(
            fn (string $cookie): string => (string) preg_replace(
                ['/^([^=]+)=[^;]*/', '/expires=[^;]+/'],
                ['$1=<opaque>', 'expires=<stamped-at>'],
                $cookie
            ),
            $headers['set-cookie']
        );
    }

    return $headers;
}

/**
 * Replace every recipient id in a captured refusal with one placeholder.
 *
 * This is the only substitution the comparison is allowed to make, and it is
 * what makes the comparison mean "indistinguishable" rather than "identical":
 * the caller already knows which id they sent, so a response that echoes it
 * back tells them nothing, while a response that says anything *else* different
 * tells them the account exists.
 *
 * The same list is applied to both records, which is what keeps it safe. The
 * placeholder holds characters no id can contain, so on every other string the
 * substitution is injective — two different values cannot be flattened into
 * one — and the only distinction it can erase is between the two ids
 * themselves, which is precisely the one being deliberately ignored. Longest
 * first so a short id cannot eat its way through a longer one.
 *
 * Integers are masked as well as strings, and not for symmetry: a refusal that
 * redirects back flashes the rejected input into the session, where the id is
 * an `int` and not a substring of anything. Leaving those alone would fail the
 * comparison on the one value it is meant to ignore.
 *
 * @param  array<string, mixed>  $observed
 * @param  list<int>  $recipientIds
 * @return array<string, mixed>
 */
function withRecipientIdsMasked(array $observed, array $recipientIds): array
{
    $ids = array_map(strval(...), $recipientIds);
    usort($ids, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

    array_walk_recursive($observed, function (mixed &$value) use ($ids, $recipientIds): void {
        if (is_string($value)) {
            $value = str_replace($ids, '<recipient-id>', $value);

            return;
        }

        if (is_int($value) && in_array($value, $recipientIds, strict: true)) {
            $value = '<recipient-id>';
        }
    });

    return $observed;
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

    /**
     * Kept although the oracle test below subsumes it. That one compares two
     * whole responses and fails with an array diff; this one names the contract
     * for an id that was never issued in a single line, and is the only place
     * that asserts nothing is opened for it *on its own* rather than after a
     * pair of requests. It is a sentence, and it costs one request.
     */
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
     *
     * **A shared status code is not the pin.** It used to be, and it would have
     * gone on passing if the two answers had started to differ in their body,
     * their redirect target, their session error bag, their flash data or their
     * Inertia props — every one of which is a bit the caller can read just as
     * well as a status line. What is compared is therefore the entire response
     * and the session it left behind, with only the recipient id substituted:
     * the caller already knows which id they sent, and nothing else may vary.
     * See `refusalToOpenThreadWith()` for what is captured, and
     * `withoutPerResponseNoise()` for the three values that cannot repeat
     * between two responses and why blanking them hides nothing.
     */
    test('answers a deactivated recipient exactly as it answers an id that was never issued', function () {
        $deactivated = User::factory()->inactive()->create();
        $neverIssuedId = 9999;

        $forDeactivated = refusalToOpenThreadWith($deactivated->getKey());
        $forNeverIssued = refusalToOpenThreadWith($neverIssuedId);

        $forDeactivated['response']->assertNotFound();
        $forNeverIssued['response']->assertNotFound();

        $recipientIds = [$deactivated->getKey(), $neverIssuedId];

        expect(withRecipientIdsMasked($forNeverIssued['observed'], $recipientIds))
            ->toBe(withRecipientIdsMasked($forDeactivated['observed'], $recipientIds));

        $this->assertDatabaseEmpty('conversations');
    });

    /**
     * The same pin on the request shape the application actually receives.
     *
     * Every other assertion on this endpoint is a document request, and the
     * "Message" button is not one: the client sends `X-Inertia`, which is a
     * different path through the response — the middleware varies on that
     * header, and an Inertia visit that ends in a 404 surfaces client-side as an
     * error overlay rather than as a rendered page (.ai/rules/messaging.md).
     * Leaving it uncovered leaves the oracle unpinned on the only shape a
     * browser will ever produce; a future error-page mapping for that overlay
     * would be free to describe the two refusals differently, and nothing would
     * notice.
     */
    test('answers both the same way on the Inertia visit the client actually makes', function () {
        $deactivated = User::factory()->inactive()->create();
        $neverIssuedId = 9999;

        $forDeactivated = refusalToOpenThreadWith($deactivated->getKey(), asInertiaVisit: true);
        $forNeverIssued = refusalToOpenThreadWith($neverIssuedId, asInertiaVisit: true);

        $forDeactivated['response']->assertNotFound();
        $forNeverIssued['response']->assertNotFound();

        $recipientIds = [$deactivated->getKey(), $neverIssuedId];

        expect(withRecipientIdsMasked($forNeverIssued['observed'], $recipientIds))
            ->toBe(withRecipientIdsMasked($forDeactivated['observed'], $recipientIds));

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
