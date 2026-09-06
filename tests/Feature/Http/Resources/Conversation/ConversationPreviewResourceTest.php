<?php

use App\Http\Resources\Conversation\ConversationPreviewResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Every key the header's messages menu reads, in the order the resource emits
 * them.
 *
 * Written out rather than derived, because the list *is* the contract: the menu
 * is client-rendered, so a key that quietly stops being emitted reads as
 * `undefined` there rather than as an error here.
 */
const PREVIEW_KEYS = ['id', 'peer', 'last_message_at', 'last_message_snippet', 'last_message_sender_id', 'unread'];

/**
 * A request carrying the given viewer, for the fallback cases: no endpoint
 * serves a preview without its relations loaded, which is the point — the
 * fallbacks exist for a loader that stops loading them.
 */
function previewRequestFrom(?User $viewer): Request
{
    $request = Request::create(route('conversations.previews'));
    $request->setUserResolver(fn (): ?User => $viewer);

    return $request;
}

/**
 * The menu as the signed-in reader receives it.
 *
 * Read through `conversations.previews` rather than built by hand: the
 * fallbacks below are only worth anything if the real loader clears them, and
 * the loader is the half of the bargain worth testing.
 *
 * @return array<string, mixed>
 */
function previewRowFor(User $reader): array
{
    return test()->actingAs($reader)
        ->getJson(route('conversations.previews'))
        ->assertOk()
        ->json('data.0');
}

test('emits exactly the six keys the menu reads', function () {
    $reader = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = Conversation::factory()->direct()->withParticipants($reader, $peer)->create();
    Message::factory()->for($conversation)->from($peer)->create();

    expect(array_keys(previewRowFor($reader)))->toBe(PREVIEW_KEYS);
});

/**
 * A thread that has been opened but never written into still has a peer, a name
 * and an avatar to draw; only the message half of the row is unknown. Emitting
 * null there rather than dropping the keys is what keeps the menu rendering
 * that row instead of a hole.
 */
test('names the peer and nulls the message half of a thread nobody has written into', function () {
    $reader = User::factory()->create();
    $peer = User::factory()->create();
    Conversation::factory()->direct()->withParticipants($reader, $peer)->create();

    $row = previewRowFor($reader);

    expect(array_keys($row))->toBe(PREVIEW_KEYS)
        ->and($row['peer']['id'])->toBe($peer->getKey())
        ->and($row['peer']['username'])->toBe($peer->username)
        ->and($row['last_message_at'])->toBeNull()
        ->and($row['last_message_snippet'])->toBeNull()
        ->and($row['last_message_sender_id'])->toBeNull()
        ->and($row['unread'])->toBeFalse();
});

/**
 * The neutral fallback, and the reason it is a fallback rather than
 * `whenLoaded()` or a lazy load: a loader that forgets `users` or `lastMessage`
 * ships a visibly empty menu — wrong in the safe direction — instead of a
 * dropped key the client reads as `undefined`, or a query per row on the one
 * endpoint every signed-in visitor fetches on every document load.
 * `Model::preventLazyLoading()` would not catch it on a menu of one row
 * (.ai/rules/app.md), so nothing else here would.
 */
test('falls back on every key rather than dropping one when the loader loaded no relations', function () {
    $reader = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = Conversation::factory()->direct()->withParticipants($reader, $peer)->create();
    Message::factory()->for($conversation)->from($peer)->create(['content' => 'Is she still available?']);

    $payload = ConversationPreviewResource::make(Conversation::query()->findOrFail($conversation->getKey()))
        ->toArray(previewRequestFrom($reader));

    expect(array_keys($payload))->toBe(PREVIEW_KEYS)
        ->and($payload['peer'])->toBeNull()
        ->and($payload['last_message_snippet'])->toBeNull()
        ->and($payload['last_message_sender_id'])->toBeNull()
        ->and($payload['unread'])->toBeFalse();
});

/**
 * `$request->user()` resolves on either guard — App\Models\Admin signs in on
 * `admins` — and every predicate the row is built from is typed against
 * App\Models\User. Narrowing to null is the honest answer as well as the typed
 * one; without it the failure mode is a TypeError while rendering.
 */
test('reports no peer and no unread flag when nobody is signed in', function () {
    $reader = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = Conversation::factory()->direct()->withParticipants($reader, $peer)->create();
    Message::factory()->for($conversation)->from($peer)->create();

    $payload = ConversationPreviewResource::make($conversation->load(['users', 'lastMessage']))
        ->toArray(previewRequestFrom(null));

    expect(array_keys($payload))->toBe(PREVIEW_KEYS)
        ->and($payload['peer'])->toBeNull()
        ->and($payload['unread'])->toBeFalse();
});

/**
 * The snippet is cut on the server, not in the dropdown: MessageResource emits
 * `content` in full against a 5,000-character ceiling, so an untruncated menu
 * of five long messages is ~25 KB on every document load.
 */
test('cuts the last message down to a snippet', function (string $content, string $expected) {
    $reader = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = Conversation::factory()->direct()->withParticipants($reader, $peer)->create();
    Message::factory()->for($conversation)->from($peer)->create(['content' => $content]);

    expect(previewRowFor($reader)['last_message_snippet'])->toBe($expected);
})->with([
    'a message shorter than the limit, left exactly as it was written' => [
        'Is she still available?',
        'Is she still available?',
    ],
    'a message longer than the limit, cut at 120 characters plus an ellipsis' => [
        str_repeat('a', 300),
        str_repeat('a', 120).'...',
    ],
]);

test('takes the length of the snippet from the configured bound', function () {
    config(['petconnect.messaging.preview_snippet_length' => 20]);
    $reader = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = Conversation::factory()->direct()->withParticipants($reader, $peer)->create();
    Message::factory()->for($conversation)->from($peer)->create(['content' => str_repeat('b', 300)]);

    expect(previewRowFor($reader)['last_message_snippet'])->toBe(str_repeat('b', 20).'...');
});
