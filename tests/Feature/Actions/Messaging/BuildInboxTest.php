<?php

use App\Actions\Messaging\BuildInbox;
use App\Http\Resources\Conversation\ConversationResource;
use App\MediaLibrary\MediaPathGenerator;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What a page of the inbox costs: the paginator's count, the conversations,
 * their participants, those participants' avatar media, the last message of
 * each, its sender, and that sender's avatar media.
 *
 * Measured rather than guessed, and the test beside it grows the inbox instead
 * of trusting the number alone — an eager load that stops covering what
 * ConversationResource walks turns into a query per row, and `users.media` is
 * the one that is easy to lose: medialibrary's `force_lazy_loading` is off
 * outside production precisely so that miss throws, but a resource that reads
 * an avatar through `getFirstMediaUrl()` on a single-row page is unguarded
 * (see .ai/rules/app.md).
 *
 * Asserted as an equality, not a ceiling: under a ceiling a regression of one
 * query passes silently until it happens to cross the bound, by which point the
 * commit that spent it is long gone.
 */
const INBOX_PAYLOAD_QUERY_COST = 7;

/**
 * Give a user the avatar UserSummaryResource reads with getFirstMediaUrl().
 *
 * The owner directory is stamped on the media row exactly as the upload
 * pipeline does, so MediaPathGenerator never falls back to looking the owner
 * up — that fallback is a query of its own and would be counted below as if it
 * were a missing eager load.
 */
function attachInboxAvatar(User $user): void
{
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $user->media_directory_name])
        ->toMediaCollection('users');
}

/**
 * Open threads for the given reader, each with a peer of its own carrying an
 * avatar and each holding one message from that peer.
 */
function seedInboxFor(User $reader, int $conversations): void
{
    for ($index = 0; $index < $conversations; $index++) {
        $peer = User::factory()->create();
        attachInboxAvatar($peer);

        $conversation = Conversation::factory()->direct()->withParticipants($reader, $peer)->create();

        Message::factory()->for($conversation)->from($peer)->create();
    }
}

/**
 * Serialise a page of the inbox and report how many queries it took.
 *
 * The payload goes all the way to JSON on purpose: a resource only walks its
 * nested resources when something encodes it, so stopping at toArray() would
 * leave every peer avatar and every last-message sender unresolved and the
 * count blind to what they cost.
 */
function countInboxQueries(User $reader): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    ConversationResource::collection(app(BuildInbox::class)->handle($reader, perPage: 50))
        ->response()
        ->getContent();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

test('returns the reader own threads and none of anybody else', function () {
    $reader = User::factory()->create();
    $mine = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();
    Conversation::factory()->direct()->withParticipants(User::factory()->create(), User::factory()->create())->create();

    $page = app(BuildInbox::class)->handle($reader);

    expect($page->pluck('id')->all())->toBe([$mine->getKey()]);
});

test('orders the inbox by the most recent message first', function () {
    $reader = User::factory()->create();
    $quiet = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();
    $busy = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();
    Message::factory()->for($quiet)->create(['created_at' => now()->subDay()]);
    Message::factory()->for($busy)->create(['created_at' => now()]);

    $page = app(BuildInbox::class)->handle($reader);

    expect($page->pluck('id')->all())->toBe([$busy->getKey(), $quiet->getKey()]);
});

test('keeps a thread that has never been written into in the list', function () {
    $reader = User::factory()->create();
    $empty = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();
    $written = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();
    Message::factory()->for($written)->create();

    $page = app(BuildInbox::class)->handle($reader);

    expect($page->pluck('id')->all())->toEqualCanonicalizing([$written->getKey(), $empty->getKey()])
        ->and($page->firstWhere('id', $empty->getKey())->last_message_at)->toBeNull();
});

test('pages the inbox at the size it is asked for', function () {
    $reader = User::factory()->create();
    for ($thread = 0; $thread < 3; $thread++) {
        Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();
    }

    $page = app(BuildInbox::class)->handle($reader, perPage: 2);

    expect($page->total())->toBe(3)
        ->and($page->perPage())->toBe(2)
        ->and($page->items())->toHaveCount(2);
});

test('marks a thread unread until the reader cursor passes its last message', function () {
    $reader = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = Conversation::factory()->direct()->withParticipants($reader, $peer)->create();
    Message::factory()->for($conversation)->from($peer)->create();

    expect(app(BuildInbox::class)->handle($reader)->first()->isUnreadFor($reader))->toBeTrue();

    $conversation->markAsReadFor($reader);

    expect(app(BuildInbox::class)->handle($reader)->first()->isUnreadFor($reader))->toBeFalse();
});

test('serialises a page of the inbox in a constant number of queries however many threads it holds', function () {
    Storage::fake(config('media-library.disk_name'));
    $reader = User::factory()->create();
    $this->actingAs($reader);

    seedInboxFor($reader, conversations: 2);

    $atTwoThreads = countInboxQueries($reader);

    seedInboxFor($reader, conversations: 4);

    $atSixThreads = countInboxQueries($reader);

    seedInboxFor($reader, conversations: 9);

    $atFifteenThreads = countInboxQueries($reader);

    expect($atTwoThreads)->toBe($atSixThreads)
        ->and($atSixThreads)->toBe($atFifteenThreads)
        ->and($atFifteenThreads)->toBe(INBOX_PAYLOAD_QUERY_COST);
});
