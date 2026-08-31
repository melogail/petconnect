<?php

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Requests\Message\UpdateMessageRequest;
use App\Http\Resources\Message\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The keys the payload emits that no message form posts back.
 *
 * Everything else it emits must be a rule of exactly the same name, because the
 * resource is the read side of the message form: a client edits a message by
 * sending back the keys it was handed. A key that drifts out of that set is
 * dropped by validated() without a word, which is how the pet form silently
 * nulled seven columns.
 *
 * @var list<string>
 */
const MESSAGE_READ_SHAPES = [
    'id',
    'conversation_id',
    'sender_id',
    'sender',
    'status',
    'is_mine',
    'is_edited',
    'edited_at',
    'is_pinned',
    'pinned_at',
    'pinned_by',
    'can_edit',
    'can_delete',
    'can_pin',
    'created_at',
    'updated_at',
];

/**
 * A request carrying the given viewer, for the cases the paging endpoint cannot
 * reach: a non-participant is refused the thread before a payload is built, so
 * the only way to ask what `can_pin` says about them is to render the resource.
 */
function messageRequestFrom(?User $viewer): Request
{
    $request = Request::create('/');
    $request->setUserResolver(fn (): ?User => $viewer);

    return $request;
}

/**
 * The newest message of a thread, as the paging endpoint emits it to the given
 * participant.
 *
 * @return array<string, mixed>
 */
function threadMessagePayload(Conversation $conversation, User $reader): array
{
    return test()
        ->actingAs($reader)
        ->get(route('conversations.messages.index', $conversation))
        ->assertOk()
        ->json('data.0');
}

/**
 * A direct thread with one message in it, sent by the returned participant.
 *
 * @return array{0: Conversation, 1: User}
 */
function threadWithOneMessage(): array
{
    $sender = User::factory()->create();
    $conversation = Conversation::factory()->direct()
        ->withParticipants($sender, User::factory()->create())
        ->create();
    Message::factory()->for($conversation)->from($sender)->create();

    return [$conversation, $sender];
}

test('every key the resource emits is either a rule on the store request or a declared read shape', function () {
    [$conversation, $sender] = threadWithOneMessage();

    $payload = threadMessagePayload($conversation, $sender);

    $unmatched = array_values(array_diff(
        array_keys($payload),
        array_keys((new StoreMessageRequest)->rules()),
        MESSAGE_READ_SHAPES,
    ));

    expect($unmatched)->toBe([]);
});

test('every writable key the resource emits carries the name the store request accepts', function () {
    [$conversation, $sender] = threadWithOneMessage();

    $payload = threadMessagePayload($conversation, $sender);
    $rules = (new StoreMessageRequest)->rules();

    $writable = array_values(array_diff(array_keys($payload), MESSAGE_READ_SHAPES));

    expect($writable)->toEqualCanonicalizing(['content', 'type']);

    foreach ($writable as $key) {
        expect($rules)->toHaveKey($key);
    }
});

test('the key an edit posts back is a rule on the update request', function () {
    [$conversation, $sender] = threadWithOneMessage();

    $payload = threadMessagePayload($conversation, $sender);

    expect((new UpdateMessageRequest)->rules())->toHaveKey('content')
        ->and($payload)->toHaveKey('content');
});

test('the update request accepts no key beyond the text, so an edit cannot move or retype a message', function () {
    expect(array_keys((new UpdateMessageRequest)->rules()))->toBe(['content']);
});

test('describes the sender with a byline and nothing that belongs to their account', function () {
    [$conversation, $sender] = threadWithOneMessage();

    $payload = threadMessagePayload($conversation, $sender);

    expect(array_keys($payload['sender']))
        ->toEqualCanonicalizing(['id', 'name', 'username', 'location', 'avatar'])
        ->and($payload['sender']['id'])->toBe($sender->getKey());
});

test('marks a message as the viewer own only for its sender', function () {
    [$conversation, $sender] = threadWithOneMessage();
    $peer = $conversation->users->firstWhere('id', '!=', $sender->getKey());

    expect(threadMessagePayload($conversation, $sender)['is_mine'])->toBeTrue()
        ->and(threadMessagePayload($conversation, $peer)['is_mine'])->toBeFalse();
});

test('emits the sender own editing and withdrawal rights, and refuses them to the other side', function () {
    [$conversation, $sender] = threadWithOneMessage();
    $peer = $conversation->users->firstWhere('id', '!=', $sender->getKey());

    expect(threadMessagePayload($conversation, $sender))
        ->can_edit->toBeTrue()
        ->can_delete->toBeTrue();

    expect(threadMessagePayload($conversation, $peer))
        ->can_edit->toBeFalse()
        ->can_delete->toBeFalse();
});

test('closes can_edit once the window has passed while leaving can_delete open', function () {
    config(['petconnect.messaging.edit_window_minutes' => 15]);
    [$conversation, $sender] = threadWithOneMessage();
    Message::query()->sole()->forceFill(['created_at' => now()->subMinutes(16)])->saveQuietly();

    expect(threadMessagePayload($conversation, $sender))
        ->can_edit->toBeFalse()
        ->can_delete->toBeTrue();
});

/**
 * Pinning is open to any participant and to either side's message —
 * MessagePolicy::pin, whose whole matrix is in
 * tests/Feature/Policies/MessagePolicyTest. What this pins is that the payload
 * carries the policy's answer rather than the frontend deriving it from
 * `is_mine`, which is what the thread page used to do.
 */
test('offers the pin to both participants, for a message either of them sent', function () {
    [$conversation, $sender] = threadWithOneMessage();
    $peer = $conversation->users->firstWhere('id', '!=', $sender->getKey());

    expect(threadMessagePayload($conversation, $sender)['can_pin'])->toBeTrue()
        ->and(threadMessagePayload($conversation, $peer)['can_pin'])->toBeTrue();
});

test('refuses the pin to a user who is not in the conversation', function () {
    threadWithOneMessage();
    $message = Message::query()->with('conversation.users')->sole();

    $payload = MessageResource::make($message)->toArray(messageRequestFrom(User::factory()->create()));

    expect($payload['can_pin'])->toBeFalse();
});

/**
 * The key is `false` rather than absent or lazily resolved when the loader did
 * not chaperone the conversation on. `whenLoaded()` would drop it and let a
 * client read `undefined` as "allowed"; reaching through the relation would be
 * a query per rendered row on the payload most likely to hold fifty of them.
 */
test('reports no pin right when the conversation was never loaded onto the message', function () {
    [, $sender] = threadWithOneMessage();
    $unchaperoned = Message::query()->sole();

    $payload = MessageResource::make($unchaperoned)->toArray(messageRequestFrom($sender));

    expect($payload)->toHaveKey('can_pin')
        ->and($payload['can_pin'])->toBeFalse();
});

test('emits the pin as an id rather than an embedded user', function () {
    $sender = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = Conversation::factory()->direct()->withParticipants($sender, $peer)->create();
    Message::factory()->for($conversation)->from($sender)->pinned($peer)->create();

    expect(threadMessagePayload($conversation, $sender))
        ->is_pinned->toBeTrue()
        ->pinned_by->toBe($peer->getKey());
});

test('emits the delivery state and the payload type as their backing values', function () {
    [$conversation, $sender] = threadWithOneMessage();

    expect(threadMessagePayload($conversation, $sender))
        ->status->toBe(MessageStatus::Sent->value)
        ->type->toBe(MessageType::Text->value);
});
