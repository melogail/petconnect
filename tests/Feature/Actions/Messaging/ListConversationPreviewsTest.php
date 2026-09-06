<?php

use App\Actions\Messaging\CountUnreadConversations;
use App\Actions\Messaging\ListConversationPreviews;
use App\Http\Resources\Conversation\ConversationPreviewResource;
use App\MediaLibrary\MediaPathGenerator;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What the header's messages menu costs: the conversations, their participants,
 * those participants' avatar media, the last message of each, and the unread
 * badge's own aggregate.
 *
 * Five, not the eight the same menu cost through BuildInbox: the dropdown needs
 * neither `lastMessage.sender.media` nor a paginator's COUNT, which is the
 * whole reason ListConversationPreviews exists beside BuildInbox rather than
 * being it at a smaller page size.
 *
 * An equality rather than a ceiling, and measured at four different limits on
 * one fixture, because both regressions this endpoint invites are invisible to
 * a ceiling. A *half*-miss — `users` loaded but not `users.media` — is a query
 * per row and only shows up as growth with the limit. A *complete* miss takes
 * the count **down**, so the payload's keys are asserted beside the number
 * (.ai/rules/tests.md); ConversationPreviewResource falls back to null rather
 * than dropping a key, so a forgotten eager load here ships a menu of blank
 * rows instead of throwing.
 */
const PREVIEW_PAYLOAD_QUERY_COST = 5;

/**
 * Give a user the avatar the preview's `peer.avatar` reads with
 * getFirstMediaUrl().
 *
 * The owner directory is stamped on the media row exactly as the upload
 * pipeline does, so MediaPathGenerator never falls back to looking the owner up
 * — that fallback is a query of its own and would be counted below as if it
 * were a missing eager load.
 */
function attachPreviewAvatar(User $user): void
{
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $user->media_directory_name])
        ->toMediaCollection('users');
}

/**
 * Open threads for the given reader, each with a peer of its own and each
 * holding one unread message from that peer.
 */
function seedPreviewThreadsFor(User $reader, int $conversations): void
{
    for ($index = 0; $index < $conversations; $index++) {
        $peer = User::factory()->create();

        $conversation = Conversation::factory()->direct()->withParticipants($reader, $peer)->create();

        Message::factory()->for($conversation)->from($peer)->create();
    }
}

/**
 * The same threads with an avatar on every peer, which is the fixture the query
 * count needs: `users.media` is the eager load that turns into a query per row
 * when it is lost, and nothing reads it unless a peer has media to find.
 */
function seedPreviewThreadsWithAvatarsFor(User $reader, int $conversations): void
{
    seedPreviewThreadsFor($reader, $conversations);

    $reader->conversations()->with('users')->get()
        ->flatMap(fn (Conversation $conversation) => $conversation->users)
        ->reject(fn (User $participant): bool => $participant->is($reader))
        ->each(attachPreviewAvatar(...));
}

/**
 * Serialise the menu exactly as ConversationController::previews does, and
 * report both what it took and what it said.
 *
 * The payload goes all the way to JSON on purpose: a resource only walks its
 * nested resources when something encodes it, so stopping at toArray() would
 * leave every peer unresolved and the count blind to the avatar it costs. The
 * badge's aggregate is inside the measured window because it is part of the one
 * response, and leaving it out would pin four queries for a request that makes
 * five.
 *
 * The reader is put on the request explicitly rather than through `actingAs()`,
 * because nothing here is an HTTP request and ConversationPreviewResource asks
 * `$request->user()` for `peer` and `unread`. Without a resolver both would
 * answer as a guest, the peer would never be resolved, and the count would be
 * blind to the avatar it costs.
 *
 * @return array{queries: int, payload: array<string, mixed>}
 */
function measurePreviewPayload(User $reader, int $limit): array
{
    $request = Request::create(route('conversations.previews'));
    $request->setUserResolver(fn (): User => $reader);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $content = ConversationPreviewResource::collection(app(ListConversationPreviews::class)->handle($reader, $limit))
        ->additional(['meta' => ['unread_count' => app(CountUnreadConversations::class)->handle($reader)]])
        ->toResponse($request)
        ->getContent();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return [
        'queries' => $queries,
        'payload' => json_decode((string) $content, associative: true),
    ];
}

test('returns the reader own threads and none of anybody else', function () {
    $reader = User::factory()->create();
    $mine = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();
    $alsoMine = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();
    Conversation::factory()->direct()
        ->withParticipants(User::factory()->create(), User::factory()->create())
        ->create();

    $previews = app(ListConversationPreviews::class)->handle($reader);

    expect($previews->pluck('id')->all())->toEqualCanonicalizing([$mine->getKey(), $alsoMine->getKey()]);
});

test('orders the menu by the most recent message first', function () {
    $reader = User::factory()->create();
    $quiet = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();
    $busy = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();
    Message::factory()->for($quiet)->create(['created_at' => now()->subDay()]);
    Message::factory()->for($busy)->create(['created_at' => now()]);

    $previews = app(ListConversationPreviews::class)->handle($reader);

    expect($previews->pluck('id')->all())->toBe([$busy->getKey(), $quiet->getKey()]);
});

test('takes the size of the menu from the configured preview page size', function () {
    config(['petconnect.messaging.preview_per_page' => 2]);
    $reader = User::factory()->create();
    seedPreviewThreadsFor($reader, conversations: 3);

    expect(app(ListConversationPreviews::class)->handle($reader))->toHaveCount(2);
});

test('honours a limit it is given over the configured one', function () {
    config(['petconnect.messaging.preview_per_page' => 5]);
    $reader = User::factory()->create();
    seedPreviewThreadsFor($reader, conversations: 4);

    expect(app(ListConversationPreviews::class)->handle($reader, limit: 3))->toHaveCount(3);
});

test('serialises the menu in a constant number of queries however many rows it asks for', function () {
    Storage::fake(config('media-library.disk_name'));
    $reader = User::factory()->create();

    seedPreviewThreadsWithAvatarsFor($reader, conversations: 15);

    $atTwo = measurePreviewPayload($reader, limit: 2);
    $atThree = measurePreviewPayload($reader, limit: 3);
    $atFive = measurePreviewPayload($reader, limit: 5);
    $atFifteen = measurePreviewPayload($reader, limit: 15);

    expect($atTwo['queries'])->toBe(PREVIEW_PAYLOAD_QUERY_COST)
        ->and($atThree['queries'])->toBe(PREVIEW_PAYLOAD_QUERY_COST)
        ->and($atFive['queries'])->toBe(PREVIEW_PAYLOAD_QUERY_COST)
        ->and($atFifteen['queries'])->toBe(PREVIEW_PAYLOAD_QUERY_COST);

    expect($atFifteen['payload']['data'])->toHaveCount(15);

    expect($atFifteen['payload']['data'][0])
        ->toHaveKeys(['id', 'peer', 'last_message_at', 'last_message_snippet', 'last_message_sender_id', 'unread'])
        ->and($atFifteen['payload']['data'][0]['peer'])->not->toBeNull()
        ->and($atFifteen['payload']['data'][0]['peer']['avatar'])->not->toBeNull()
        ->and($atFifteen['payload']['data'][0]['last_message_snippet'])->not->toBeNull()
        ->and($atFifteen['payload']['meta'])->toBe(['unread_count' => 15]);
});
