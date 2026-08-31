<?php

use App\Http\Resources\Conversation\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;

/**
 * Open a direct thread between the reader and the given peer.
 *
 * The threads below are read through `conversations.index` rather than built by
 * hand, because `can_send` is only free — and only true — when the loader has
 * put `users` on the conversation, and the loader is the half of the bargain
 * worth testing.
 */
function threadBetween(User $reader, User $peer): Conversation
{
    return Conversation::factory()->direct()->withParticipants($reader, $peer)->create();
}

/**
 * A request carrying the given viewer, for the fallback case: there is no
 * endpoint that serves a conversation without its participants, which is the
 * point — the fallback exists for a loader that stops doing so.
 */
function conversationRequestFrom(?User $viewer): Request
{
    $request = Request::create('/');
    $request->setUserResolver(fn (): ?User => $viewer);

    return $request;
}

/**
 * `messages.store` authorizes through MessagePolicy::create — verified sender,
 * in this thread, and the other side still accepting from them. Without this
 * key the composer rendered unconditionally and the refusal arrived as a 403 on
 * submit, after the message had been written.
 *
 * The policy's own matrix is tests/Feature/Policies/MessagePolicyTest's. What
 * these pin is that the payload carries the policy's answer and that the
 * loaders keep it answerable, which is what stops the frontend deriving it a
 * second time (.ai/rules/policies.md).
 */
test('offers the composer to a participant of a live thread', function () {
    $reader = User::factory()->create();
    threadBetween($reader, User::factory()->create());

    $this->actingAs($reader)
        ->get(route('conversations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('conversations.data.0.can_send', true));
});

/**
 * Deactivate the peer and the honest answer is "this thread is read-only", not
 * an error on send. The row stays in the inbox: the correspondence is still
 * readable, only the composer closes.
 */
test('closes the composer when the other side has been deactivated', function () {
    $reader = User::factory()->create();
    threadBetween($reader, User::factory()->inactive()->create());

    $this->actingAs($reader)
        ->get(route('conversations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('conversations.data.0.can_send', false)
            ->has('conversations.data.0.participants', 2));
});

test('closes the composer for a user who has not verified their email', function () {
    $reader = User::factory()->unverified()->create();
    $conversation = threadBetween($reader, User::factory()->create());

    $payload = ConversationResource::make($conversation->load('users'))
        ->toArray(conversationRequestFrom($reader));

    expect($payload['can_send'])->toBeFalse();
});

/**
 * The key is `false` rather than absent or lazily resolved when the loader did
 * not load `users`. A loader that forgets the relation ships a read-only
 * looking thread — visible and wrong in the safe direction — instead of a
 * participation query per row on the payload that holds fifty of them.
 */
test('reports no send right when the participants were never loaded', function () {
    $reader = User::factory()->create();
    $conversation = threadBetween($reader, User::factory()->create());

    $payload = ConversationResource::make(Conversation::query()->findOrFail($conversation->getKey()))
        ->toArray(conversationRequestFrom($reader));

    expect($payload)->toHaveKey('can_send')
        ->and($payload['can_send'])->toBeFalse();
});

/**
 * The thread page asks the same question of the same key, through a different
 * loader — LoadConversationParticipants rather than BuildInbox — so both are
 * pinned.
 */
test('offers the composer on the thread page as well as in the inbox', function () {
    $reader = User::factory()->create();
    $conversation = threadBetween($reader, User::factory()->create());

    $this->actingAs($reader)
        ->get(route('conversations.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('conversation.can_send', true));
});
