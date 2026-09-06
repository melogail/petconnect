<?php

use App\Actions\Profiles\LoadProfileForDisplay;
use App\MediaLibrary\MediaPathGenerator;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Like;
use App\Models\Pet;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What the public profile costs to assemble: the person, a page of their
 * listings and a page of the reviews about them.
 *
 * Flat, because the rating summary and the card counts are `withCount()` /
 * `withAvg()` subqueries on queries already being issued rather than loaded
 * rows, and because both collections page. The legacy show did
 * `$user->load(['pets', 'reviews' => ...])` with no bound on either, so a
 * profile with 400 listings shipped all 400.
 *
 * A count alone cannot see every miss — dropping `category.media` moves a query
 * rather than adding one, and dropping an eager load *entirely* makes the count
 * go **down** while `whenLoaded()` silently omits the key. The keys are
 * asserted in tests/Feature/Http/Controllers/Web/ProfileControllerTest, which is
 * where the payload is serialised.
 *
 * Asserted as an equality, not a ceiling: under a ceiling a regression of one
 * query passes silently until it happens to cross the bound, by which point the
 * commit that spent it is long gone.
 */
const PROFILE_ACTION_QUERY_COST = 12;

/**
 * Give a user the avatar ProfileResource and ReviewAuthorResource read with
 * getFirstMediaUrl().
 *
 * The owner directory is copied onto the media row exactly as
 * UploadProfileImage does it, so MediaPathGenerator never falls back to looking
 * the owner up — that fallback is a query of its own and would be counted below
 * as if it were an eager-loading miss.
 */
function attachProfileAvatar(User $user): void
{
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $user->media_directory_name])
        ->toMediaCollection('users');
}

/**
 * Hang listings on a profile, each with a category of its own carrying an image,
 * a named breed and a cover photo — every relation the listing cards walk.
 */
function seedProfileListings(User $profile, int $listings): void
{
    for ($index = 0; $index < $listings; $index++) {
        $category = Category::factory()->create();
        $category->addMedia(UploadedFile::fake()->image('icon.jpg'))->toMediaCollection('categories');

        $pet = Pet::factory()
            ->for($profile)
            ->for($category)
            ->for(Breed::factory()->for($category))
            ->create();

        $pet->addMedia(UploadedFile::fake()->image('cover.jpg'))
            ->withCustomProperties([
                Pet::FEATURED_PROPERTY => true,
                MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $profile->media_directory_name,
            ])
            ->toMediaCollection(Pet::PHOTO_COLLECTION);
    }
}

/**
 * Write reviews about a profile, each by an author of its own carrying an
 * avatar.
 */
function seedProfileReviews(User $profile, int $reviews): void
{
    for ($index = 0; $index < $reviews; $index++) {
        $author = User::factory()->create();
        attachProfileAvatar($author);

        Review::factory()->for($author)->forUser($profile)->create();
    }
}

function countProfileLoadQueries(User $profile, ?User $viewer): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    app(LoadProfileForDisplay::class)->handle($profile, $viewer);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

test('assembles the page in a constant number of queries however many listings and reviews the profile holds', function () {
    $profile = User::factory()->create();
    attachProfileAvatar($profile);
    seedProfileListings($profile, 2);
    seedProfileReviews($profile, 2);

    $atTwo = countProfileLoadQueries($profile, null);

    seedProfileListings($profile, 4);
    seedProfileReviews($profile, 4);

    $atSix = countProfileLoadQueries($profile, null);

    expect($atTwo)->toBe($atSix)
        ->and($atSix)->toBe(PROFILE_ACTION_QUERY_COST);
});

/**
 * `withReviewStats()` had no caller until this page. It adds `reviews_count`
 * and `reviews_avg_rate` as subqueries on the query already being issued, so
 * the header's rating costs no extra round trip and no loaded rows.
 */
test('summarises the reputation on the profile row itself', function () {
    $profile = User::factory()->create();
    Review::factory()->forUser($profile)->rating(5)->create();
    Review::factory()->forUser($profile)->rating(2)->create();
    Pet::factory()->count(3)->for($profile)->create();

    $loaded = app(LoadProfileForDisplay::class)->handle($profile, null);

    expect($loaded['user']->reviews_count)->toBe(2)
        ->and(round((float) $loaded['user']->reviews_avg_rate, 2))->toBe(3.5)
        ->and($loaded['user']->pets_count)->toBe(3);
});

test('lists only the listings the profile owns, newest first, and hides the retired ones', function () {
    $profile = User::factory()->create();
    $older = Pet::factory()->for($profile)->create(['created_at' => now()->subDay()]);
    $newer = Pet::factory()->for($profile)->create(['created_at' => now()]);
    Pet::factory()->for($profile)->create()->delete();
    Pet::factory()->create();

    $loaded = app(LoadProfileForDisplay::class)->handle($profile, null);

    expect($loaded['listings']->pluck('id')->all())->toBe([$newer->getKey(), $older->getKey()]);
});

/**
 * Read through the model's own `reviews()` relation, so no morph value is built
 * by hand: `reviewable_type` holds the alias `user`, and a class-name
 * comparison would match nothing and say nothing about it.
 */
test('lists only the reviews written about this profile', function () {
    $profile = User::factory()->create();
    $aboutThem = Review::factory()->forUser($profile)->create();
    Review::factory()->for($profile)->create();
    Review::factory()->create();

    $loaded = app(LoadProfileForDisplay::class)->handle($profile, null);

    expect($loaded['reviews']->pluck('id')->all())->toBe([$aboutThem->getKey()]);
});

test('marks the listings and the profile the viewer has already liked', function () {
    $profile = User::factory()->create();
    $viewer = User::factory()->create();
    $liked = Pet::factory()->for($profile)->create();
    $unliked = Pet::factory()->for($profile)->create();
    Like::factory()->for($viewer)->forPet($liked)->create();
    Like::factory()->for($viewer)->forUser($profile)->create();

    $loaded = app(LoadProfileForDisplay::class)->handle($profile, $viewer);

    expect((bool) $loaded['user']->is_liked)->toBeTrue()
        ->and($loaded['listings']->firstWhere('id', $liked->getKey())->is_liked)->toBeTruthy()
        ->and($loaded['listings']->firstWhere('id', $unliked->getKey())->is_liked)->toBeFalsy();
});

/**
 * `has_reviewed` closes a real gap: the unique index on `reviews` and
 * SubmitReview\EnsureNotAlreadyReviewed have always refused a second review of
 * the same person by the same author, but nothing on the read side said so, so
 * the form was offered to everybody and the refusal arrived as a validation
 * error after the user had written one.
 *
 * A review *about* the viewer, and one they wrote about somebody else, both sit
 * in the fixture: the flag asks a two-sided question and either half alone
 * would let a `where` on the wrong column pass.
 */
test('marks a profile the viewer has already reviewed, and only for that viewer', function () {
    $profile = User::factory()->create();
    $author = User::factory()->create();
    $bystander = User::factory()->create();
    Review::factory()->for($author)->forUser($profile)->create();
    Review::factory()->for($profile)->forUser($bystander)->create();

    expect((bool) app(LoadProfileForDisplay::class)->handle($profile, $author)['user']->has_reviewed)->toBeTrue()
        ->and((bool) app(LoadProfileForDisplay::class)->handle($profile, $bystander)['user']->has_reviewed)->toBeFalse()
        ->and(app(LoadProfileForDisplay::class)->handle($profile, null)['user']->has_reviewed)->toBeFalsy();
});

/**
 * `withLikedBy()` and `withReviewedBy()` are `withExists` subqueries on the
 * single row this Action was already fetching, not two more queries. A signed
 * in visit therefore costs exactly what a guest's does — the cheapest way for
 * either to regress into a round trip is to be measured against the other.
 */
test('costs a signed in viewer no more than a guest, because both viewer flags are subqueries', function () {
    $profile = User::factory()->create();
    attachProfileAvatar($profile);
    seedProfileListings($profile, 2);
    seedProfileReviews($profile, 2);
    $viewer = User::factory()->create();
    Like::factory()->for($viewer)->forUser($profile)->create();
    Review::factory()->for($viewer)->forUser($profile)->create();

    expect(countProfileLoadQueries($profile, $viewer))
        ->toBe(countProfileLoadQueries($profile, null));
});
