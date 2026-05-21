<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Pet;
use App\Models\User;

function createDirectConversation(User $a, User $b): Conversation
{
    $conversation = Conversation::factory()->create(['type' => 'direct']);
    $conversation->users()->attach([$a->id => [], $b->id => []]);

    return $conversation;
}

it('redirects guests from the conversations index', function () {
    $this->get(route('conversations.index'))->assertRedirect(route('login'));
});

it('creates a direct conversation between two users via store', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $response = $this->actingAs($a)->post(route('conversations.store'), [
        'other_user_id' => $b->id,
    ]);

    $response->assertRedirect();

    expect(Conversation::query()->where('type', 'direct')->count())->toBe(1);

    $conversation = Conversation::query()->first();

    expect($conversation->users()->count())->toBe(2);
});

it('rejects starting a conversation with yourself', function () {
    $a = User::factory()->create();

    $this->actingAs($a)->post(route('conversations.store'), [
        'other_user_id' => $a->id,
    ])->assertSessionHasErrors('other_user_id');
});

it('allows a participant to send a message and updates last_message_at', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = createDirectConversation($a, $b);

    $response = $this->actingAs($a)->post(route('conversations.messages.store', $conversation), [
        'content' => 'Hello there',
    ]);

    $response->assertRedirect(route('conversations.show', $conversation));

    $conversation->refresh();

    expect($conversation->last_message_at)->not->toBeNull();
    expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(1);
});

it('forbids a non-participant from posting to a conversation', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $stranger = User::factory()->create();
    $conversation = createDirectConversation($a, $b);

    $this->actingAs($stranger)->post(route('conversations.messages.store', $conversation), [
        'content' => 'Intruder',
    ])->assertForbidden();
});

it('forbids viewing a conversation you are not in', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $stranger = User::factory()->create();
    $conversation = createDirectConversation($a, $b);

    $this->actingAs($stranger)->get(route('conversations.show', $conversation))->assertForbidden();
});

it('allows mark as read to set pivot last_read_at', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = createDirectConversation($a, $b);

    $this->actingAs($a)->post(route('conversations.read', $conversation))->assertRedirect();

    $pivot = $conversation->users()->whereKey($a->id)->first()->pivot;

    expect($pivot->last_read_at)->not->toBeNull();
});

it('allows the sender to update their message', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = createDirectConversation($a, $b);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $a->id,
        'content' => 'Original',
        'type' => 'text',
        'status' => 'sent',
    ]);

    $this->actingAs($a)->put(route('messages.update', $message), [
        'content' => 'Updated body',
    ])->assertRedirect(route('conversations.show', $conversation));

    expect($message->fresh()->content)->toBe('Updated body');
});

it('forbids a non-sender from updating a message', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = createDirectConversation($a, $b);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $a->id,
        'content' => 'Original',
        'type' => 'text',
        'status' => 'sent',
    ]);

    $this->actingAs($b)->put(route('messages.update', $message), [
        'content' => 'Hacked',
    ])->assertForbidden();
});

it('allows the sender to delete their message', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = createDirectConversation($a, $b);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $a->id,
        'content' => 'To remove',
        'type' => 'text',
        'status' => 'sent',
    ]);

    $this->actingAs($a)->delete(route('messages.destroy', $message))->assertRedirect(route('conversations.show', $conversation));

    expect($message->fresh()->trashed())->toBeTrue();
});

it('forbids a non-sender from deleting a message', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = createDirectConversation($a, $b);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $a->id,
        'content' => 'Stay',
        'type' => 'text',
        'status' => 'sent',
    ]);

    $this->actingAs($b)->delete(route('messages.destroy', $message))->assertForbidden();
});

it('reuses an existing direct conversation for the same pair', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $this->actingAs($a)->post(route('conversations.store'), [
        'other_user_id' => $b->id,
    ])->assertRedirect();

    $firstId = Conversation::query()->value('id');

    $this->actingAs($a)->post(route('conversations.store'), [
        'other_user_id' => $b->id,
    ])->assertRedirect();

    expect(Conversation::query()->count())->toBe(1);
    expect(Conversation::query()->value('id'))->toBe($firstId);
});

it('inertia shares null messaging for guests', function () {
    $version = file_exists($manifest = public_path('build/manifest.json'))
        ? hash_file('xxh128', $manifest)
        : '';

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
    ])->get(route('home'));

    $response->assertOk();
    expect($response->json('props.messaging'))->toBeNull();
});

it('inertia shares messaging summary for authenticated users', function () {
    $user = User::factory()->create();

    $version = file_exists($manifest = public_path('build/manifest.json'))
        ? hash_file('xxh128', $manifest)
        : '';

    $response = $this->actingAs($user)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
    ])->get(route('home'));

    $response->assertOk();
    $messaging = $response->json('props.messaging');
    expect($messaging)->toBeArray()
        ->and($messaging)->toHaveKeys(['unread_count', 'previews'])
        ->and($messaging['unread_count'])->toBeInt()
        ->and($messaging['previews'])->toBeArray();
});

it('does not include unrelated users in a conversation started for a pet owner', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $stranger = User::factory()->create();

    $pet = Pet::factory()->create(['user_id' => $seller->id]);

    expect($pet->user_id)->toBe($seller->id);

    $this->actingAs($buyer)->post(route('conversations.store'), [
        'other_user_id' => $seller->id,
        'initial_message' => 'Question about your pet listing',
    ])->assertRedirect();

    $conversation = Conversation::query()->first();

    $memberIds = $conversation->users->pluck('id')->sort()->values()->all();

    expect($memberIds)->toEqual(collect([$buyer->id, $seller->id])->sort()->values()->all());
    expect($conversation->users->where('id', $stranger->id))->toHaveCount(0);
});

it('isolates messages between different conversation threads', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $c = User::factory()->create();

    $convAB = createDirectConversation($a, $b);
    $convAC = createDirectConversation($a, $c);

    Message::factory()->create([
        'conversation_id' => $convAB->id,
        'sender_id' => $a->id,
        'content' => 'Only for B thread',
        'type' => 'text',
        'status' => 'sent',
    ]);

    Message::factory()->create([
        'conversation_id' => $convAC->id,
        'sender_id' => $a->id,
        'content' => 'Only for C thread',
        'type' => 'text',
        'status' => 'sent',
    ]);

    expect(Message::query()->where('conversation_id', $convAB->id)->where('content', 'Only for C thread')->exists())->toBeFalse();
    expect(Message::query()->where('conversation_id', $convAC->id)->where('content', 'Only for B thread')->exists())->toBeFalse();
});
