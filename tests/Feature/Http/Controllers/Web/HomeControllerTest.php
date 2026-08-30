<?php

use App\Models\Category;
use App\Models\Comment;
use App\Models\Pet;
use Illuminate\Support\Facades\DB;
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
