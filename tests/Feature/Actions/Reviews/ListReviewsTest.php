<?php

use App\Actions\Reviews\ListReviews;
use App\Enums\Reviewable;
use App\Http\Resources\Review\ReviewResource;
use App\MediaLibrary\MediaPathGenerator;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * What a page of a profile's reviews costs: resolving the target, the
 * paginator's count, the reviews, their authors and those authors' avatars.
 *
 * Measured, not guessed, and flat — `has_reported` is a withExists() subquery
 * on the query already being issued, so it adds a column rather than a round
 * trip. The test beside the ceiling grows the page instead of trusting the
 * number alone.
 */
const REVIEWS_PAGE_QUERY_CEILING = 5;

/**
 * Give a user the avatar ReviewAuthorResource reads with getFirstMediaUrl().
 *
 * The owner directory is stamped on the media row exactly as the upload
 * pipeline does, so MediaPathGenerator never falls back to looking the owner
 * up — that fallback is a query of its own and would be counted below as if it
 * were a missing eager load.
 */
function attachReviewerAvatar(User $user): void
{
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $user->media_directory_name])
        ->toMediaCollection('users');
}

/**
 * Hang reviews on a profile, each written by an author of its own carrying an
 * avatar. Distinct authors are not decoration: `reviews` is unique per
 * (user, target), so one author cannot write two.
 */
function seedReviewsAbout(User $subject, int $count): void
{
    for ($index = 0; $index < $count; $index++) {
        $author = User::factory()->create();
        attachReviewerAvatar($author);

        Review::factory()->for($author)->forUser($subject)->create();
    }
}

/**
 * Serialise a page of reviews and report both what it cost and what it said.
 *
 * The payload goes all the way to JSON on purpose: a resource only walks its
 * nested resources when something encodes it, so stopping at toArray() would
 * leave every author avatar unresolved and the count blind to what it costs.
 *
 * @return array{queries: int, payload: array<string, mixed>}
 */
function measureReviewsPage(User $subject, ?User $viewer = null): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $json = ReviewResource::collection(app(ListReviews::class)->handle(
        reviewableType: Reviewable::User,
        reviewableId: $subject->getKey(),
        viewer: $viewer,
    ))->response()->getContent();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return ['queries' => $queries, 'payload' => json_decode((string) $json, true)];
}

test('returns the reviews written about the target, newest first', function () {
    $subject = User::factory()->create();
    $older = Review::factory()->forUser($subject)->create(['created_at' => '2026-01-01 10:00:00']);
    $newer = Review::factory()->forUser($subject)->create(['created_at' => '2026-01-02 10:00:00']);

    $page = app(ListReviews::class)->handle(Reviewable::User, $subject->getKey());

    expect($page->pluck('id')->all())->toBe([$newer->getKey(), $older->getKey()]);
});

/**
 * Newest-first with an `id` tiebreak is the house convention for thread-shaped
 * lists: several reviews can share a `created_at` to the second, and a sort
 * with ties is not a stable paginator.
 */
test('breaks a tie on the created timestamp with the newest id first', function () {
    $subject = User::factory()->create();
    $first = Review::factory()->forUser($subject)->create(['created_at' => '2026-01-01 10:00:00']);
    $second = Review::factory()->forUser($subject)->create(['created_at' => '2026-01-01 10:00:00']);

    $page = app(ListReviews::class)->handle(Reviewable::User, $subject->getKey());

    expect($page->pluck('id')->all())->toBe([$second->getKey(), $first->getKey()]);
});

test('excludes reviews written about somebody else', function () {
    $subject = User::factory()->create();
    $mine = Review::factory()->forUser($subject)->create();
    Review::factory()->forUser(User::factory()->create())->create();

    $page = app(ListReviews::class)->handle(Reviewable::User, $subject->getKey());

    expect($page->pluck('id')->all())->toBe([$mine->getKey()]);
});

test('pages at the configured size', function () {
    config(['petconnect.reviews.per_page' => 2]);
    $subject = User::factory()->create();
    seedReviewsAbout($subject, 3);

    $page = app(ListReviews::class)->handle(Reviewable::User, $subject->getKey());

    expect($page->count())->toBe(2)
        ->and($page->total())->toBe(3);
});

test('marks the reviews the viewer has already reported', function () {
    $viewer = User::factory()->create();
    $subject = User::factory()->create();
    $reported = Review::factory()->forUser($subject)->create();
    $untouched = Review::factory()->forUser($subject)->create();
    Report::factory()->for($viewer)->forReportable($reported)->create();

    $page = app(ListReviews::class)->handle(Reviewable::User, $subject->getKey(), $viewer);

    expect($page->firstWhere('id', $reported->getKey())->has_reported)->toBeTrue()
        ->and($page->firstWhere('id', $untouched->getKey())->has_reported)->toBeFalse();
});

test('leaves has_reported off the page for a guest, and the payload reads it as false', function () {
    $subject = User::factory()->create();
    seedReviewsAbout($subject, 2);

    $page = measureReviewsPage($subject);

    expect($page['payload']['data'][0]['has_reported'])->toBeFalse();
});

test('raises a model not found exception for a target that does not exist', function () {
    expect(fn () => app(ListReviews::class)->handle(Reviewable::User, 9999))
        ->toThrow(ModelNotFoundException::class);
});

/**
 * The ceiling is flat because every extra fact the page needs is a subquery on
 * a query already being issued. Three page sizes, because a per-row query only
 * shows up as growth.
 */
test('costs the same number of queries however many reviews are on the page', function (int $reviews) {
    $subject = User::factory()->create();
    seedReviewsAbout($subject, $reviews);
    config(['petconnect.reviews.per_page' => $reviews]);

    $page = measureReviewsPage($subject, User::factory()->create());

    expect($page['queries'])->toBe(REVIEWS_PAGE_QUERY_CEILING);
})->with([
    '2 reviews' => 2,
    '10 reviews' => 10,
    '25 reviews' => 25,
]);

/**
 * The assertion the query count cannot make.
 *
 * ReviewResource emits the author through `whenLoaded('user')`, so dropping the
 * eager load entirely does not lazy load and does not throw — it silently drops
 * the `author` key and the count goes *down*. Neither preventLazyLoading nor
 * the ceiling above sees it, which is why this asserts the key. See
 * .ai/rules/resources.md.
 */
test('serialises every review with its author, so a dropped eager load cannot pass as a cheaper page', function () {
    $subject = User::factory()->create();
    seedReviewsAbout($subject, 2);

    $page = measureReviewsPage($subject);

    expect($page['payload']['data'])->toHaveCount(2)
        ->and($page['payload']['data'][0])->toHaveKey('author')
        ->and($page['payload']['data'][0]['author'])->toHaveKeys(['id', 'name', 'username', 'location', 'avatar'])
        ->and($page['payload']['data'][1])->toHaveKey('author');
});
