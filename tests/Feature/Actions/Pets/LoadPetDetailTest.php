<?php

use App\Actions\Pets\LoadPetDetail;
use App\Http\Resources\Pet\PetDetailResource;
use App\MediaLibrary\MediaPathGenerator;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What LoadPetDetail::handle() plus serialisation costs: one query for the
 * listing and one for each relation the Action eager loads. Measured against
 * the fixture below rather than guessed, so an eager load that stops covering
 * what a resource walks pushes the count past it.
 *
 * This is the Action's number, not the page's — the detail *route* costs more,
 * and DETAIL_ROUTE_QUERY_CEILING is the figure to reach for when asking what a
 * visit to a listing costs.
 *
 * A count alone cannot see every miss, which is why the second test exists.
 * Dropping a single-parent eager load such as `category.media` moves one query
 * from the load to the serialisation instead of adding one, and Eloquent only
 * arms Model::preventLazyLoading() on a result set of more than one row, so
 * nothing throws either. Only the thread — many comment authors, many repliers
 * — turns a missing eager load into a count that grows.
 */
const DETAIL_ACTION_QUERY_CEILING = 13;

/**
 * What a visit to the listing page costs end to end: the Action's queries plus
 * the two PetController::show adds around them — route model binding resolving
 * `{pet}`, and RecordPetView::handle() incrementing the counter.
 *
 * Measured through the route so the number means "the detail page", which the
 * Action's ceiling above does not: read as the page's cost it is two queries
 * short and any regression baseline drawn from it starts out wrong.
 */
const DETAIL_ROUTE_QUERY_CEILING = 15;

/**
 * Give a user the avatar PetOwnerResource reads with getFirstMediaUrl().
 *
 * The owner directory is copied onto the media row exactly as the upload
 * pipeline does it, so MediaPathGenerator never falls back to looking the owner
 * up — that fallback is a query of its own and would be counted below as if it
 * were an eager-loading miss.
 */
function attachDetailAvatar(User $user): void
{
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $user->media_directory_name])
        ->toMediaCollection('users');
}

/**
 * A listing with an owner, a taxonomy, a photo and avatars everywhere — every
 * relation the detail payload walks.
 */
function listingUnderDetail(User $owner): Pet
{
    attachDetailAvatar($owner);

    $category = Category::factory()->create();
    $category->addMedia(UploadedFile::fake()->image('icon.jpg'))
        ->toMediaCollection('categories');

    $pet = Pet::factory()
        ->for($owner)
        ->for($category)
        ->for(Breed::factory()->for($category))
        ->create();

    $pet->addMedia(UploadedFile::fake()->image('cover.jpg'))
        ->withCustomProperties([
            Pet::FEATURED_PROPERTY => true,
            MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $owner->media_directory_name,
        ])
        ->toMediaCollection(Pet::PHOTO_COLLECTION);

    return $pet;
}

/**
 * Hang comments on a listing, each written by an author of its own carrying an
 * avatar, and each with replies by further authors.
 */
function seedCommentThread(Pet $pet, int $comments, int $repliesEach): void
{
    for ($comment = 0; $comment < $comments; $comment++) {
        $author = User::factory()->create();
        attachDetailAvatar($author);

        $parent = Comment::factory()->for($author)->for($pet, 'commentable')->create();

        for ($reply = 0; $reply < $repliesEach; $reply++) {
            $replier = User::factory()->create();
            attachDetailAvatar($replier);

            Comment::factory()->for($replier)->reply($parent)->create();
        }
    }
}

/**
 * Build the payload the detail page renders, and report how many queries it
 * took.
 *
 * The payload is serialised all the way to JSON on purpose: a resource only
 * walks its nested resources when something encodes it, so stopping at
 * toArray() would leave every comment author's avatar unresolved and the count
 * blind to the queries they cost.
 */
function countPetDetailQueries(Pet $pet, ?User $viewer): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    PetDetailResource::make(app(LoadPetDetail::class)->handle($pet, $viewer))
        ->response()
        ->getContent();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

/**
 * Render the listing page as a signed-in visitor and report how many queries
 * the request took.
 *
 * The cache is flushed first because RecordPetView counts a visitor once per
 * window: a second measurement with the same visitor would skip the increment
 * and come back one query short of the first for a reason that has nothing to
 * do with what is being measured. The visitor is not the owner, for the same
 * reason — an owner's visit is never counted.
 */
function countPetDetailRouteQueries(Pet $pet, User $visitor): int
{
    Cache::flush();

    test()->actingAs($visitor);

    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->get(route('pets.show', $pet))->assertOk();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

test('serialises the detail payload in a constant number of queries however long the comment thread is', function () {
    Storage::fake(config('media-library.disk_name'));
    $owner = User::factory()->create();
    $pet = listingUnderDetail($owner);
    $this->actingAs($owner);

    seedCommentThread($pet, comments: 2, repliesEach: 2);

    $atTwoComments = countPetDetailQueries($pet, $owner);

    seedCommentThread($pet, comments: 3, repliesEach: 2);

    $atFiveComments = countPetDetailQueries($pet, $owner);

    expect($atTwoComments)->toBe($atFiveComments)
        ->and($atFiveComments)->toBeLessThanOrEqual(DETAIL_ACTION_QUERY_CEILING);
});

test('renders the listing page in a constant number of queries however long the comment thread is', function () {
    Storage::fake(config('media-library.disk_name'));
    $owner = User::factory()->create();
    $pet = listingUnderDetail($owner);
    $visitor = User::factory()->create();

    seedCommentThread($pet, comments: 2, repliesEach: 2);

    $atTwoComments = countPetDetailRouteQueries($pet, $visitor);

    seedCommentThread($pet, comments: 3, repliesEach: 2);

    $atFiveComments = countPetDetailRouteQueries($pet, $visitor);

    expect($atTwoComments)->toBe($atFiveComments)
        ->and($atFiveComments)->toBeLessThanOrEqual(DETAIL_ROUTE_QUERY_CEILING);
});

test('serialises the detail payload without a further query, because every relation it walks is eager loaded', function () {
    Storage::fake(config('media-library.disk_name'));
    $owner = User::factory()->create();
    $pet = listingUnderDetail($owner);
    $this->actingAs($owner);
    seedCommentThread($pet, comments: 2, repliesEach: 2);
    $loaded = app(LoadPetDetail::class)->handle($pet, $owner);

    DB::flushQueryLog();
    DB::enableQueryLog();

    PetDetailResource::make($loaded)->response()->getContent();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});
