<?php

use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;

/**
 * The keys the payload emits that no comment form posts back.
 *
 * Everything else it emits must be a rule of exactly the same name, because the
 * resource is the read side of the comment form: a client edits a comment by
 * sending back the keys it was handed. A key that drifts out of that set is
 * dropped by validated() without a word, which is how the pet form silently
 * nulled seven columns.
 *
 * @var list<string>
 */
const COMMENT_READ_SHAPES = [
    'id',
    'author',
    'is_author',
    'likes_count',
    'is_liked',
    'replies_count',
    'has_reported',
    'replies',
    'created_at',
    'updated_at',
];

/**
 * The first comment of a listing's thread, as the endpoint emits it.
 *
 * @return array<string, mixed>
 */
function threadPayload(Pet $pet): array
{
    return test()
        ->get(route('comments.index', ['commentable_type' => 'pet', 'commentable_id' => $pet->getKey()]))
        ->assertOk()
        ->json('data.0');
}

test('every key the resource emits is either a rule on the store request or a declared read shape', function () {
    $pet = Pet::factory()->create();
    Comment::factory()->for($pet, 'commentable')->create();

    $payload = threadPayload($pet);

    $unmatched = array_values(array_diff(
        array_keys($payload),
        array_keys((new StoreCommentRequest)->rules()),
        COMMENT_READ_SHAPES,
    ));

    expect($unmatched)->toBe([]);
});

test('every writable key the resource emits carries the name the store request accepts', function () {
    $pet = Pet::factory()->create();
    $parent = Comment::factory()->for($pet, 'commentable')->create();
    Comment::factory()->reply($parent)->create();

    $payload = threadPayload($pet);
    $rules = (new StoreCommentRequest)->rules();

    $writable = array_values(array_diff(array_keys($payload), COMMENT_READ_SHAPES));

    expect($writable)->toEqualCanonicalizing(['content', 'parent_id']);

    foreach ($writable as $key) {
        expect($rules)->toHaveKey($key);
    }
});

test('the key an edit posts back is a rule on the update request', function () {
    $pet = Pet::factory()->create();
    Comment::factory()->for($pet, 'commentable')->create();

    $payload = threadPayload($pet);

    expect((new UpdateCommentRequest)->rules())->toHaveKey('content')
        ->and($payload)->toHaveKey('content');
});

test('describes the author with a byline and nothing that belongs to their account', function () {
    $author = User::factory()->create();
    $pet = Pet::factory()->create();
    Comment::factory()->for($author)->for($pet, 'commentable')->create();

    $payload = threadPayload($pet);

    expect(array_keys($payload['author']))
        ->toEqualCanonicalizing(['id', 'name', 'username', 'location', 'avatar'])
        ->and($payload['author']['id'])->toBe($author->getKey());
});

test('marks a comment as the viewer own only for its author', function () {
    $author = User::factory()->create();
    $pet = Pet::factory()->create();
    Comment::factory()->for($author)->for($pet, 'commentable')->create();

    expect(threadPayload($pet)['is_author'])->toBeFalse();

    $this->actingAs($author);

    expect(threadPayload($pet)['is_author'])->toBeTrue();
});

test('omits the replies key on a comment whose replies were not loaded', function () {
    $parent = Comment::factory()->create();
    Comment::factory()->reply($parent)->create();

    $payload = $this->get(route('comments.replies', $parent))
        ->assertOk()
        ->json('data.0');

    expect($payload)->not->toHaveKey('replies')
        ->and($payload)->toHaveKey('replies_count');
});
