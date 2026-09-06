<?php

use App\Actions\Messaging\CountUnreadConversations;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The badge is one aggregate and no models are hydrated to reach it, however
 * many threads the reader is in.
 *
 * An equality rather than a ceiling, for the reason INBOX_PAYLOAD_QUERY_COST
 * gives beside the inbox: under a ceiling the day this starts hydrating a row
 * per conversation — which is what every obvious rewrite of it does — passes
 * silently until it happens to cross the bound.
 */
const UNREAD_COUNT_QUERY_COST = 1;

/**
 * The same question asked the other way: read the reader's conversations and
 * filter them in PHP with the model's own predicate.
 *
 * This is the definition of unread in this application
 * (Conversation::isUnreadFor), and CountUnreadConversations is a hand-written
 * SQL restatement of it — two spellings of one rule, in two languages, which
 * nothing but the dataset below keeps in step. It is deliberately the
 * expression the Action's docblock quotes as the loader it replaces, so a
 * disagreement names the clause that drifted rather than a number.
 */
function unreadCountedByTheModel(User $reader): int
{
    return $reader->conversations()
        ->get()
        ->filter(fn (Conversation $conversation): bool => $conversation->isUnreadFor($reader))
        ->count();
}

/**
 * Move the reader's read cursor in `conversation_user` to the given instant.
 */
function moveReadCursor(Conversation $conversation, User $reader, ?DateTimeInterface $readAt): void
{
    $conversation->users()->updateExistingPivot($reader->getKey(), ['last_read_at' => $readAt]);
}

/**
 * Open a thread for the reader with a peer of their own, each holding one
 * message from that peer, so several unread rows exist at once.
 */
function seedUnreadThreadsFor(User $reader, int $conversations): void
{
    for ($index = 0; $index < $conversations; $index++) {
        $peer = User::factory()->create();

        $conversation = Conversation::factory()->direct()->withParticipants($reader, $peer)->create();

        Message::factory()->for($conversation)->from($peer)->create();
    }
}

/**
 * Every edge of the unread predicate, arranged on one thread.
 *
 * Each case receives a reader, a peer and the direct conversation between them,
 * and says what the answer must be. The test asserts that answer twice: once
 * against the Action's SQL and once against Conversation::isUnreadFor(), which
 * is what makes this a drift guard rather than a restatement of either.
 */
dataset('unread edge cases', [
    'a thread nobody has written into yet' => [
        function (User $reader, User $peer, Conversation $conversation): void {},
        false,
    ],

    'a message from the peer the reader has never opened' => [
        function (User $reader, User $peer, Conversation $conversation): void {
            Message::factory()->for($conversation)->from($peer)->create();
        },
        true,
    ],

    'a cursor written in the same instant as the message it acknowledges' => [
        function (User $reader, User $peer, Conversation $conversation): void {
            Message::factory()->for($conversation)->from($peer)->create();

            moveReadCursor($conversation, $reader, $conversation->fresh()->last_message_at);
        },
        false,
    ],

    'a cursor one second older than the message' => [
        function (User $reader, User $peer, Conversation $conversation): void {
            Message::factory()->for($conversation)->from($peer)->create();

            moveReadCursor($conversation, $reader, now()->subSecond());
        },
        true,
    ],

    'the reader own message last, on a stale cursor' => [
        function (User $reader, User $peer, Conversation $conversation): void {
            Message::factory()->for($conversation)->from($peer)->create(['created_at' => now()->subMinute()]);
            Message::factory()->for($conversation)->from($reader)->create();

            moveReadCursor($conversation, $reader, now()->subHour());
        },
        false,
    ],

    'the reader own last message deleted, uncovering the peer message beneath it' => [
        function (User $reader, User $peer, Conversation $conversation): void {
            Message::factory()->for($conversation)->from($peer)->create(['created_at' => now()->subMinute()]);
            $ownMessage = Message::factory()->for($conversation)->from($reader)->create();

            moveReadCursor($conversation, $reader, now()->subHour());
            $ownMessage->delete();
        },
        true,
    ],

    'that deleted message restored again' => [
        function (User $reader, User $peer, Conversation $conversation): void {
            Message::factory()->for($conversation)->from($peer)->create(['created_at' => now()->subMinute()]);
            $ownMessage = Message::factory()->for($conversation)->from($reader)->create();

            moveReadCursor($conversation, $reader, now()->subHour());
            $ownMessage->delete();
            $ownMessage->restore();
        },
        false,
    ],

    'every message in the thread deleted' => [
        function (User $reader, User $peer, Conversation $conversation): void {
            Message::factory()->count(2)->for($conversation)->from($peer)->create()
                ->each(fn (Message $message) => $message->delete());
        },
        false,
    ],

    'an unread thread that has since been deleted' => [
        function (User $reader, User $peer, Conversation $conversation): void {
            Message::factory()->for($conversation)->from($peer)->create();

            $conversation->delete();
        },
        false,
    ],

    'two messages in the same second, the reader holding the higher id' => [
        function (User $reader, User $peer, Conversation $conversation): void {
            Message::factory()->for($conversation)->from($peer)->create();
            Message::factory()->for($conversation)->from($reader)->create();
        },
        false,
    ],

    'two messages in the same second, the peer holding the higher id' => [
        function (User $reader, User $peer, Conversation $conversation): void {
            Message::factory()->for($conversation)->from($reader)->create();
            Message::factory()->for($conversation)->from($peer)->create();
        },
        true,
    ],
]);

test('counts a thread unread exactly when the model does', function (Closure $arrange, bool $unread) {
    $this->freezeTime();
    $reader = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = Conversation::factory()->direct()->withParticipants($reader, $peer)->create();

    $arrange($reader, $peer, $conversation);

    $counted = app(CountUnreadConversations::class)->handle($reader);

    expect($counted)->toBe($unread ? 1 : 0)
        ->and($counted)->toBe(unreadCountedByTheModel($reader));
})->with('unread edge cases');

test('counts every unread thread the reader is in and none of anybody else', function () {
    $reader = User::factory()->create();
    seedUnreadThreadsFor($reader, conversations: 2);
    $read = Conversation::factory()->direct()->withParticipants($reader, $peer = User::factory()->create())->create();
    Message::factory()->for($read)->from($peer)->create();
    $read->markAsReadFor($reader);

    $stranger = User::factory()->create();
    seedUnreadThreadsFor($stranger, conversations: 3);

    expect(app(CountUnreadConversations::class)->handle($reader))->toBe(2)
        ->and(unreadCountedByTheModel($reader))->toBe(2);
});

test('answers in one query however many threads the reader is in', function () {
    $reader = User::factory()->create();
    seedUnreadThreadsFor($reader, conversations: 2);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $atTwoThreads = app(CountUnreadConversations::class)->handle($reader);
    $queriesAtTwoThreads = count(DB::getQueryLog());
    DB::disableQueryLog();

    seedUnreadThreadsFor($reader, conversations: 6);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $atEightThreads = app(CountUnreadConversations::class)->handle($reader);
    $queriesAtEightThreads = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($atTwoThreads)->toBe(2)
        ->and($atEightThreads)->toBe(8)
        ->and($queriesAtTwoThreads)->toBe(UNREAD_COUNT_QUERY_COST)
        ->and($queriesAtEightThreads)->toBe(UNREAD_COUNT_QUERY_COST);
});
