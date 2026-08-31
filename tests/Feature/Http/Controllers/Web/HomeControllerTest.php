<?php

use App\MediaLibrary\MediaPathGenerator;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Load the feed and report how many queries it took.
 */
function countFeedQueries(TestCase $test, int $perPage): int
{
    config(['petconnect.pets.feed_per_page' => $perPage]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $test->get(route('home'))->assertOk();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

test('a guest can browse the feed', function () {
    $pet = Pet::factory()->available()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
            ->has('pets.data', 1)
            ->where('pets.data.0.id', $pet->getKey()));
});

test('the feed omits listings that are no longer available', function () {
    $available = Pet::factory()->available()->create();
    Pet::factory()->unavailable()->create();

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('pets.data', 1)
            ->where('pets.data.0.id', $available->getKey()));
});

test('a category filter in the query string narrows the feed', function () {
    $category = Category::factory()->create();
    $wanted = Pet::factory()->available()->for($category)->create();
    Pet::factory()->available()->create();

    $this->get(route('home', ['category_ids' => [$category->getKey()]]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('pets.data', 1)
            ->where('pets.data.0.id', $wanted->getKey()));
});

test('the feed costs the same number of queries whatever the page size', function () {
    $pets = Pet::factory()->available()->count(30)->create();

    foreach ($pets->take(6) as $pet) {
        Comment::factory()->for($pet, 'commentable')->create();
    }

    $atTwelve = countFeedQueries($this, 12);
    $atTwentyFour = countFeedQueries($this, 24);

    expect($atTwelve)->toBe($atTwentyFour)
        ->and($atTwelve)->toBeLessThanOrEqual(12);
});

/**
 * Give a user the avatar the feed card's comment bylines read with
 * getFirstMediaUrl(), stamping the owner directory the way the upload pipeline
 * does so MediaPathGenerator never looks the owner up again — that fallback is
 * a query of its own and would count here as a missing eager load.
 */
function attachFeedAvatar(User $user): void
{
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $user->media_directory_name])
        ->toMediaCollection('users');
}

/**
 * Comment on every listing in the feed, each comment written by an author of
 * its own carrying an avatar.
 *
 * @param  Collection<int, Pet>  $pets
 */
function commentOnEveryListing(Collection $pets): void
{
    foreach ($pets as $pet) {
        $author = User::factory()->create();
        attachFeedAvatar($author);

        Comment::factory()->for($author)->for($pet, 'commentable')->create();
    }
}

test('the feed costs the same number of queries however many comments its cards carry', function () {
    Storage::fake(config('media-library.disk_name'));
    $pets = Pet::factory()->available()->count(3)->create();

    commentOnEveryListing($pets);

    $atOneCommentEach = countFeedQueries($this, 12);

    commentOnEveryListing($pets);
    commentOnEveryListing($pets);

    $atThreeCommentsEach = countFeedQueries($this, 12);

    expect($atOneCommentEach)->toBe($atThreeCommentsEach)
        ->and($atThreeCommentsEach)->toBeLessThanOrEqual(12);
});
