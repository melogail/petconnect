<?php

use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The three models that opt into the trait. A like is a morph row, so the
 * behaviour has to hold for each of them rather than for whichever one a test
 * happened to pick.
 */
dataset('likeables', [
    'a listing' => [fn (): Model => Pet::factory()->create()],
    'a comment' => [fn (): Model => Comment::factory()->create()],
    'a profile' => [fn (): Model => User::factory()->create()],
]);

/**
 * `likes` carries a unique index on (user_id, likeable_id, likeable_type), so
 * two taps arriving together make `firstOrCreate()` throw a
 * UniqueConstraintViolationException — the read and the insert are not atomic
 * and the loser of the race inserts second. `like()` uses `createOrFirst()`
 * instead, which attempts the insert and recovers from the violation by reading
 * the row that won.
 *
 * A second call is the reachable stand-in for that race: it takes the recovery
 * branch, because the row already exists. Actions\Likes\ToggleLike never issues
 * one — it checks `isLikedBy()` first — which is exactly why the recovery has no
 * coverage anywhere else, and why swapping `createOrFirst()` back for
 * `firstOrCreate()` would look harmless until a listing went busy enough for two
 * taps to land in the same millisecond.
 */
test('returns the existing like rather than a second row when the same user likes twice', function (Closure $makeLikeable) {
    $likeable = $makeLikeable();
    $user = User::factory()->create();

    $first = $likeable->like($user);
    $second = $likeable->like($user);

    expect($second->getKey())->toBe($first->getKey())
        ->and(Like::query()->whereBelongsTo($user)->whereMorphedTo('likeable', $likeable)->count())->toBe(1);
})->with('likeables');

/**
 * The index is per (user, target), not per user or per target, so two people
 * liking the same thing is two rows and one person liking two things is two
 * rows. Stated because a unique index written one column short would still pass
 * the test above.
 */
test('keeps a like per user and per target', function () {
    $pet = Pet::factory()->create();
    $otherPet = Pet::factory()->create();
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $pet->like($user);
    $pet->like($otherUser);
    $otherPet->like($user);

    expect(Like::query()->count())->toBe(3);
});

/**
 * `unlike()` reports whether it removed anything, which is what lets a caller
 * tell "the like is gone" from "there was nothing to remove" without a second
 * query.
 */
test('reports whether unliking removed anything', function (Closure $makeLikeable) {
    $likeable = $makeLikeable();
    $user = User::factory()->create();

    $likeable->like($user);

    expect($likeable->unlike($user))->toBeTrue()
        ->and($likeable->unlike($user))->toBeFalse()
        ->and($likeable->isLikedBy($user))->toBeFalse();
})->with('likeables');
