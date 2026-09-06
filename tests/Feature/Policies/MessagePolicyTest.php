<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A direct conversation between the two given users, with no messages in it.
 */
function conversationBetween(User $first, User $second): Conversation
{
    return Conversation::factory()->direct()->withParticipants($first, $second)->create();
}

describe('create', function () {
    test('a verified participant may send into the conversation', function () {
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());

        expect($sender->can('create', [Message::class, $conversation]))->toBeTrue();
    });

    test('an unverified participant may not send into the conversation', function () {
        $sender = User::factory()->unverified()->create();
        $conversation = conversationBetween($sender, User::factory()->create());

        expect($sender->can('create', [Message::class, $conversation]))->toBeFalse();
    });

    test('a user who is not in the conversation may not send into it', function () {
        $conversation = conversationBetween(User::factory()->create(), User::factory()->create());

        expect(User::factory()->create()->can('create', [Message::class, $conversation]))->toBeFalse();
    });

    /**
     * Consent is asked once when a thread is opened, so a peer who deactivates
     * afterwards is only protected here and in the send pipeline's
     * EnsureRecipientAccepts. Every other test in this file opens the thread
     * between two active users, which is the state that never exercises it.
     */
    test('a participant may not send into a thread whose peer has since deactivated', function () {
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->inactive()->create());

        expect($sender->can('create', [Message::class, $conversation]))->toBeFalse();
    });
});

describe('update', function () {
    test('the sender may edit their own message inside the window', function () {
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect($sender->can('update', $message))->toBeTrue();
    });

    test('the other participant may not edit a message they did not send', function () {
        $sender = User::factory()->create();
        $peer = User::factory()->create();
        $conversation = conversationBetween($sender, $peer);
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect($peer->can('update', $message))->toBeFalse();
    });

    test('a user who is not in the conversation may not edit a message', function () {
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect(User::factory()->create()->can('update', $message))->toBeFalse();
    });

    test('an unverified sender may not edit their own message', function () {
        $sender = User::factory()->unverified()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect($sender->can('update', $message))->toBeFalse();
    });

    test('the window is still open one minute before it closes', function () {
        config(['petconnect.messaging.edit_window_minutes' => 15]);
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)
            ->create(['created_at' => now()->subMinutes(14)]);

        expect($sender->can('update', $message))->toBeTrue();
    });

    test('the window is closed the moment the configured minutes have elapsed', function () {
        config(['petconnect.messaging.edit_window_minutes' => 15]);
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)
            ->create(['created_at' => now()->subMinutes(15)]);

        expect($sender->can('update', $message))->toBeFalse();
    });

    test('a window of zero minutes makes a message immutable the moment it is sent', function () {
        config(['petconnect.messaging.edit_window_minutes' => 0]);
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect($sender->can('update', $message))->toBeFalse();
    });
});

describe('delete', function () {
    test('the sender may withdraw their own message', function () {
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect($sender->can('delete', $message))->toBeTrue();
    });

    test('withdrawing is not windowed, so the sender may still delete a year old message', function () {
        config(['petconnect.messaging.edit_window_minutes' => 15]);
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)
            ->create(['created_at' => now()->subYear()]);

        expect($sender->can('update', $message))->toBeFalse()
            ->and($sender->can('delete', $message))->toBeTrue();
    });

    test('the other participant may not withdraw a message they did not send', function () {
        $sender = User::factory()->create();
        $peer = User::factory()->create();
        $conversation = conversationBetween($sender, $peer);
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect($peer->can('delete', $message))->toBeFalse();
    });

    test('a user who is not in the conversation may not withdraw a message', function () {
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect(User::factory()->create()->can('delete', $message))->toBeFalse();
    });
});

describe('pin', function () {
    test('the sender may pin their own message', function () {
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect($sender->can('pin', $message))->toBeTrue();
    });

    test('the other participant may pin a message they did not send', function () {
        $sender = User::factory()->create();
        $peer = User::factory()->create();
        $conversation = conversationBetween($sender, $peer);
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect($peer->can('pin', $message))->toBeTrue();
    });

    test('a user who is not in the conversation may not pin a message', function () {
        $sender = User::factory()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect(User::factory()->create()->can('pin', $message))->toBeFalse();
    });

    test('an unverified participant may not pin a message', function () {
        $sender = User::factory()->unverified()->create();
        $conversation = conversationBetween($sender, User::factory()->create());
        $message = Message::factory()->for($conversation)->from($sender)->create();

        expect($sender->can('pin', $message))->toBeFalse();
    });
});

/**
 * MessageResource asks `update` and `delete` once per rendered message, so a
 * participation query in either would be thirty extra queries on a thread page
 * and nothing in the suite would notice — Gate calls are invisible to
 * preventLazyLoading. See .ai/rules/policies.md.
 */
test('deciding whether a message may be edited or withdrawn costs no query', function (string $ability) {
    $sender = User::factory()->create();
    $conversation = conversationBetween($sender, User::factory()->create());
    Message::factory()->for($conversation)->from($sender)->create();
    $message = Message::query()->sole();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $sender->can($ability, $message);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
})->with(['update', 'delete']);
