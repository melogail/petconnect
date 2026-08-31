<?php

use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;

/**
 * The keys the payload emits that no review form posts back.
 *
 * Everything else it emits must be a rule of exactly the same name, because the
 * resource is the read side of the review form: a client edits a review by
 * sending back the keys it was handed. A key that drifts out of that set is
 * dropped by validated() without a word, which is how the pet form silently
 * nulled seven columns.
 *
 * @var list<string>
 */
const REVIEW_READ_SHAPES = [
    'id',
    'author',
    'is_author',
    'has_reported',
    'can_edit',
    'can_delete',
    'created_at',
    'updated_at',
];

/**
 * The first review of a profile's page, as the endpoint emits it.
 *
 * @return array<string, mixed>
 */
function reviewPayload(User $subject): array
{
    return test()
        ->get(route('reviews.index', ['reviewable_type' => 'user', 'reviewable_id' => $subject->getKey()]))
        ->assertOk()
        ->json('data.0');
}

test('every key the resource emits is either a rule on the store request or a declared read shape', function () {
    $subject = User::factory()->create();
    Review::factory()->forUser($subject)->create();

    $payload = reviewPayload($subject);

    $unmatched = array_values(array_diff(
        array_keys($payload),
        array_keys((new StoreReviewRequest)->rules()),
        REVIEW_READ_SHAPES,
    ));

    expect($unmatched)->toBe([]);
});

test('every writable key the resource emits carries the name the store request accepts', function () {
    $subject = User::factory()->create();
    Review::factory()->forUser($subject)->create();

    $payload = reviewPayload($subject);
    $rules = (new StoreReviewRequest)->rules();

    $writable = array_values(array_diff(array_keys($payload), REVIEW_READ_SHAPES));

    expect($writable)->toEqualCanonicalizing(['rate', 'comment']);

    foreach ($writable as $key) {
        expect($rules)->toHaveKey($key);
    }
});

test('every key an edit posts back is a rule on the update request', function () {
    $subject = User::factory()->create();
    Review::factory()->forUser($subject)->create();

    $payload = reviewPayload($subject);
    $rules = (new UpdateReviewRequest)->rules();

    foreach (array_diff(array_keys($payload), REVIEW_READ_SHAPES) as $key) {
        expect($rules)->toHaveKey($key)
            ->and($payload)->toHaveKey($key);
    }
});

test('describes the author with a byline and nothing that belongs to their account', function () {
    $author = User::factory()->create();
    $subject = User::factory()->create();
    Review::factory()->for($author)->forUser($subject)->create();

    $payload = reviewPayload($subject);

    expect(array_keys($payload['author']))
        ->toEqualCanonicalizing(['id', 'name', 'username', 'location', 'avatar'])
        ->and($payload['author']['id'])->toBe($author->getKey());
});

test('marks a review as the viewer own only for its author', function () {
    $author = User::factory()->create();
    $subject = User::factory()->create();
    Review::factory()->for($author)->forUser($subject)->create();

    expect(reviewPayload($subject)['is_author'])->toBeFalse();

    $this->actingAs($author);

    expect(reviewPayload($subject)['is_author'])->toBeTrue();
});

test('offers the edit and delete controls to the author alone', function () {
    $author = User::factory()->create();
    $subject = User::factory()->create();
    Review::factory()->for($author)->forUser($subject)->create();

    $this->actingAs($subject);

    expect(reviewPayload($subject))
        ->can_edit->toBeFalse()
        ->can_delete->toBeFalse();

    $this->actingAs($author);

    expect(reviewPayload($subject))
        ->can_edit->toBeTrue()
        ->can_delete->toBeTrue();
});

test('offers a guest neither control', function () {
    $subject = User::factory()->create();
    Review::factory()->forUser($subject)->create();

    expect(reviewPayload($subject))
        ->can_edit->toBeFalse()
        ->can_delete->toBeFalse()
        ->has_reported->toBeFalse();
});

test('marks a review the viewer has already reported', function () {
    $viewer = User::factory()->create();
    $subject = User::factory()->create();
    $review = Review::factory()->forUser($subject)->create();
    Report::factory()->for($viewer)->forReportable($review)->create();

    $this->actingAs($viewer);

    expect(reviewPayload($subject)['has_reported'])->toBeTrue();
});
