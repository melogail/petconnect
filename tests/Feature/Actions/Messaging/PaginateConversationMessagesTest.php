<?php

use App\Actions\Messaging\PaginateConversationMessages;
use App\Http\Resources\Message\MessageResource;
use App\MediaLibrary\MediaPathGenerator;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What a page of a thread costs, exactly: the participants
 * `loadMissing('users')` fetches, the paginator's count, the messages, their
 * senders, and those senders' avatar media.
 *
 * Measured rather than guessed, and asserted as an equality rather than a
 * ceiling — the two eager loads behind it fail in opposite directions and a
 * ceiling only catches one of them. `sender.media` is the load that grows the
 * count when it goes: MessageResource emits the sender through
 * UserSummaryResource, which reads an avatar with `getFirstMediaUrl()`, so
 * losing it is one query per message. `loadMissing('users')` is the one that
 * *shrinks* it: drop it and `can_pin` silently falls to `false` for every row
 * on the page while the number goes down to 4, which a ceiling would applaud.
 *
 * This is the cost on `conversations.messages.index`, where `{conversation}`
 * arrives straight from a route binding with nothing loaded. On
 * `conversations.show` the participants are already on the instance —
 * LoadConversationParticipants put them there — and `loadMissing` is free, so
 * that page pays 4.
 */
const THREAD_PAGE_QUERIES = 5;

/**
 * Give a user the avatar UserSummaryResource reads with getFirstMediaUrl().
 *
 * The owner directory is stamped on the media row exactly as the upload
 * pipeline does, so MediaPathGenerator never falls back to looking the owner
 * up — that fallback is a query of its own and would be counted below as if it
 * were a missing eager load.
 */
function attachThreadPageAvatar(User $user): void
{
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $user->media_directory_name])
        ->toMediaCollection('users');
}

/**
 * Write messages into the thread, each from a sender of its own carrying an
 * avatar, so a lost `sender.media` shows up as a query per message rather than
 * being hidden behind one shared, already-loaded sender.
 */
function seedThreadPage(Conversation $conversation, int $messages): void
{
    for ($index = 0; $index < $messages; $index++) {
        $sender = User::factory()->create();
        attachThreadPageAvatar($sender);

        Message::factory()->for($conversation)->from($sender)->create();
    }
}

/**
 * Serialise a page of the thread, and report both what came out and how many
 * queries it took.
 *
 * **The conversation is re-read from the database first, and that is the point
 * of this helper.** `loadMissing('users')` costs a query the first time it is
 * asked of an instance and nothing afterwards, so measuring three thread sizes
 * against one `$conversation` object would price the first run at 5 and the
 * next two at 4 — a fixture artefact that reads exactly like a per-row cost
 * appearing and disappearing. Every measurement starts from the state a route
 * binding hands the controller: a conversation with no relations loaded.
 *
 * The payload goes all the way to JSON on purpose: a resource only walks its
 * nested resources when something encodes it, so stopping at toArray() would
 * leave every sender avatar unresolved and the count blind to what they cost.
 *
 * The reader is put on the request explicitly rather than through `actingAs()`,
 * because nothing here is an HTTP request and the resource asks
 * `$request->user()` for the three viewer-relative keys it emits — `is_mine`,
 * `can_edit`/`can_delete` and `can_pin`. Without a resolver they would all
 * answer as a guest and the page would look correct while proving nothing.
 *
 * @return array{queries: int, messages: list<array<string, mixed>>}
 */
function serialiseThreadPage(Conversation $conversation, User $reader): array
{
    $routeBound = Conversation::query()->findOrFail($conversation->getKey());

    $request = Request::create(route('conversations.messages.index', $conversation));
    $request->setUserResolver(fn (): User => $reader);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $content = MessageResource::collection(app(PaginateConversationMessages::class)->handle($routeBound, perPage: 100))
        ->toResponse($request)
        ->getContent();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return [
        'queries' => $queries,
        'messages' => json_decode((string) $content, true)['data'],
    ];
}

test('pages the thread newest first', function () {
    $conversation = Conversation::factory()->direct()->create();
    $older = Message::factory()->for($conversation)->create(['created_at' => now()->subHour()]);
    $newer = Message::factory()->for($conversation)->create(['created_at' => now()]);

    $page = app(PaginateConversationMessages::class)->handle($conversation);

    expect($page->pluck('id')->all())->toBe([$newer->getKey(), $older->getKey()]);
});

/**
 * Several messages can share a `created_at` to the second — two quick taps, a
 * seeder, a test — and a sort with ties is not a stable paginator: the same row
 * can appear on two pages or on none. The primary key settles it.
 */
test('breaks a tie on the send time with the newest id first', function () {
    $conversation = Conversation::factory()->direct()->create();
    $sentTogether = now();
    $first = Message::factory()->for($conversation)->create(['created_at' => $sentTogether]);
    $second = Message::factory()->for($conversation)->create(['created_at' => $sentTogether]);
    $third = Message::factory()->for($conversation)->create(['created_at' => $sentTogether]);

    $page = app(PaginateConversationMessages::class)->handle($conversation);

    expect($page->pluck('id')->all())
        ->toBe([$third->getKey(), $second->getKey(), $first->getKey()]);
});

test('leaves out the messages of another thread', function () {
    $conversation = Conversation::factory()->direct()->create();
    $wanted = Message::factory()->for($conversation)->create();
    Message::factory()->for(Conversation::factory()->direct()->create())->create();

    $page = app(PaginateConversationMessages::class)->handle($conversation);

    expect($page->pluck('id')->all())->toBe([$wanted->getKey()]);
});

test('leaves out a withdrawn message', function () {
    $conversation = Conversation::factory()->direct()->create();
    $kept = Message::factory()->for($conversation)->create(['created_at' => now()->subHour()]);
    Message::factory()->for($conversation)->create(['created_at' => now()])->delete();

    $page = app(PaginateConversationMessages::class)->handle($conversation);

    expect($page->pluck('id')->all())->toBe([$kept->getKey()]);
});

test('puts the end of the thread on page one and walks backwards from there', function () {
    $conversation = Conversation::factory()->direct()->create();
    $messages = collect(range(1, 5))->map(fn (int $minute): Message => Message::factory()
        ->for($conversation)
        ->create(['created_at' => now()->subMinutes(6 - $minute)]));

    $firstPage = app(PaginateConversationMessages::class)->handle($conversation, perPage: 2);

    expect($firstPage->total())->toBe(5)
        ->and($firstPage->pluck('id')->all())
        ->toBe([$messages[4]->getKey(), $messages[3]->getKey()]);
});

test('serialises a page of the thread in a constant number of queries however many messages it holds', function () {
    Storage::fake(config('media-library.disk_name'));
    $reader = User::factory()->create();
    $conversation = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();

    seedThreadPage($conversation, messages: 3);

    $atThreeMessages = serialiseThreadPage($conversation, $reader);

    seedThreadPage($conversation, messages: 27);

    $atThirtyMessages = serialiseThreadPage($conversation, $reader);

    seedThreadPage($conversation, messages: 30);

    $atSixtyMessages = serialiseThreadPage($conversation, $reader);

    expect($atThreeMessages['queries'])->toBe(THREAD_PAGE_QUERIES)
        ->and($atThirtyMessages['queries'])->toBe(THREAD_PAGE_QUERIES)
        ->and($atSixtyMessages['queries'])->toBe(THREAD_PAGE_QUERIES);
});

/**
 * Both eager loads this Action exists for fail *quietly*: `sender` is emitted
 * through `whenLoaded()`, so a complete miss drops the key and takes the query
 * count down with it, and `can_pin` falls back to `false` rather than reaching
 * for an unloaded conversation. Neither is visible from a count, so the payload
 * itself has to be asserted (.ai/rules/tests.md).
 */
test('carries the sender byline and the pin right onto every message of the page', function () {
    Storage::fake(config('media-library.disk_name'));
    $reader = User::factory()->create();
    $conversation = Conversation::factory()->direct()->withParticipants($reader, User::factory()->create())->create();

    seedThreadPage($conversation, messages: 3);

    $messages = serialiseThreadPage($conversation, $reader)['messages'];

    expect($messages)->toHaveCount(3);

    foreach ($messages as $message) {
        expect($message)->toHaveKey('sender')
            ->and($message['sender'])->toHaveKey('avatar')
            ->and($message['can_pin'])->toBeTrue();
    }
});
