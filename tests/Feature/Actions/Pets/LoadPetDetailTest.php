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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What the detail payload costs: one query for the listing and one for each
 * relation the action eager loads. Measured against the fixture below rather
 * than guessed, so an eager load that stops covering what a resource walks
 * pushes the count past it.
 *
 * A count alone cannot see every miss, which is why the second test exists.
 * Dropping a single-parent eager load such as `category.media` moves one query
 * from the load to the serialisation instead of adding one, and Eloquent only
 * arms Model::preventLazyLoading() on a result set of more than one row, so
 * nothing throws either. Only the thread — many comment authors, many repliers
 * — turns a missing eager load into a count that grows.
 */
const DETAIL_PAYLOAD_QUERY_CEILING = 13;

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
    $user->addMedia(UploadedFile::fake()->create('avatar.jpg', 10))
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
    $category->addMedia(UploadedFile::fake()->create('icon.jpg', 10))
        ->toMediaCollection('categories');

    $pet = Pet::factory()
        ->for($owner)
        ->for($category)
        ->for(Breed::factory()->for($category))
        ->create();

    $pet->addMedia(UploadedFile::fake()->create('cover.jpg', 10))
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
        ->and($atFiveComments)->toBeLessThanOrEqual(DETAIL_PAYLOAD_QUERY_CEILING);
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
