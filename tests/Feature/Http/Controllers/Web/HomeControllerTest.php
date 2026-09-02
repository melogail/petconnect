<?php

use App\Enums\ListingType;
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
 * What one load of the feed costs, whatever is on it: the listing page plus one
 * query per relation ListHomeFeedPets eager loads, and the `withCount` /
 * `withExists` subqueries ride on those rather than adding round trips.
 *
 * Asserted as an equality rather than a ceiling. This is the frontend's only
 * performance contract for the busiest page in the application, and under a
 * ceiling a regression of one query passes silently until it happens to cross
 * the bound — by which point the commit that spent it is long gone. The bound
 * this replaces read 12 while the page measured 11, so it had already absorbed
 * one query's worth of drift in exactly that way.
 */
const FEED_QUERY_COST = 11;

/**
 * The keys PetCardResource emits behind `whenLoaded()`, which are the reason a
 * count alone is not enough here (.ai/rules/tests.md): a *complete* miss of an
 * eager load drops the key from the payload and takes the query count **down**,
 * so the count agrees with the regression. Their presence is checked on every
 * measurement below.
 *
 * @var list<string>
 */
const FEED_CARD_EAGER_LOADED_KEYS = ['category', 'breed', 'user', 'comments'];

/**
 * Load the feed and report how many queries it took, having first checked that
 * every relation the card walks actually reached the payload.
 *
 * The leading card is required to carry at least one comment, and that is not
 * decoration. `comments.user` and `comments.user.media` are nested eager loads,
 * and Eloquent skips a nested load whose parent set came back empty — so a
 * measured page with no comments on it costs two queries *fewer* and reads as a
 * regression in the count. Every caller below arranges a commented listing at
 * the top of the feed; this is what makes a fixture that stops doing so fail by
 * name rather than by arithmetic.
 */
function countFeedQueries(TestCase $test, int $perPage): int
{
    config(['petconnect.pets.feed_per_page' => $perPage]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $test->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('pets.data.0', fn (AssertableInertia $card) => $card
                ->hasAll(FEED_CARD_EAGER_LOADED_KEYS)
                ->has('comments.0')
                ->etc()));

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

/**
 * The commented listings are given an explicitly newer `created_at` rather than
 * being whichever six the loop happened to reach first, and that is the whole
 * fixture. The feed orders by `created_at` descending
 * (Pipelines\Pets\BuildHomeFeed\ApplyNearbyOrRecency) and PetFactory sets no
 * timestamp, so thirty rows created in one call share one second and the page
 * is decided entirely by the storage engine's tie-break — which changed when
 * `pets_status_deleted_at_created_at_index` landed, from the first-inserted
 * twelve to the last-inserted twelve.
 *
 * That took the commented rows off the measured page, and with no comments in
 * the result `comments.user` and `comments.user.media` are eager loads with an
 * empty parent set that Eloquent skips: 11 queries became 9, and the pin looked
 * like the regression. The number was right and the fixture was wrong. With the
 * commented rows newest they lead both page sizes, so the count measures a page
 * that actually walks the comment relations.
 */
test('the feed costs the same number of queries whatever the page size', function () {
    $this->freezeTime();

    Pet::factory()->available()->count(24)->create(['created_at' => now()->subDay()]);

    $commented = Pet::factory()->available()->count(6)->create(['created_at' => now()]);

    foreach ($commented as $pet) {
        Comment::factory()->for($pet, 'commentable')->create();
    }

    $atTwelve = countFeedQueries($this, 12);
    $atTwentyFour = countFeedQueries($this, 24);

    expect($atTwelve)->toBe($atTwentyFour)
        ->and($atTwelve)->toBe(FEED_QUERY_COST);
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
        ->and($atThreeCommentsEach)->toBe(FEED_QUERY_COST);
});

/**
 * The filter sheet cannot draw a radius slider or an age slider for bounds it
 * has not been told, and every one of those numbers is configurable, so the
 * page reads them through ListHomeFeedRequest rather than the client
 * hardcoding a second copy that drifts.
 *
 * Asserted against re-`config()`d values rather than the defaults: a prop
 * frozen at today's default agrees with exactly the drift this exists to catch.
 */
test('ships the filter bounds the sheet draws its sliders from', function () {
    config([
        'petconnect.nearby.default_radius_km' => 25.5,
        'petconnect.nearby.min_radius_km' => 3.5,
        'petconnect.nearby.max_radius_km' => 80.5,
        'petconnect.filters.max_age_years' => 18.5,
        'petconnect.filters.default_age_min' => 1.5,
        'petconnect.filters.default_age_max' => 9.5,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filterBounds', [
                'default_radius_km' => 25.5,
                'min_radius_km' => 3.5,
                'max_radius_km' => 80.5,
                'max_age_years' => 18.5,
                'default_age_min' => 1.5,
                'default_age_max' => 9.5,
            ])
            ->etc());
});

/**
 * The sheet reopens with what the visitor picked still selected, and the only
 * record of that is the query string echoed back. Normalised through
 * `filters()`, so what the page renders is what the feed query received rather
 * than the raw input.
 */
test('echoes the applied filters back so the sheet reopens with them selected', function () {
    $category = Category::factory()->create();

    $this->get(route('home', [
        'category_ids' => [$category->getKey()],
        'listing_types' => [ListingType::Sale->value],
        'age_min' => 1.5,
        'age_max' => 4.5,
        'vaccinated' => '1',
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters', [
                'category_ids' => [$category->getKey()],
                'breed_ids' => [],
                'age_min' => 1.5,
                'age_max' => 4.5,
                'listing_types' => [ListingType::Sale->value],
                'vaccinated' => true,
            ])
            ->where('nearby', false)
            ->where('radius', null)
            ->etc());
});

/**
 * A link built from one selected chip arrives as `?category_ids=7`, not as a
 * repeated parameter, so prepareForValidation lifts a bare value into the list
 * the rules and the pipeline both expect. Without it the `array` rule rejects
 * the visitor's own shared link.
 */
test('accepts a single filter value where a list is expected', function () {
    $category = Category::factory()->create();
    $wanted = Pet::factory()->available()->for($category)->create();
    Pet::factory()->available()->create();

    $this->get(route('home', ['category_ids' => $category->getKey()]))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.category_ids', [$category->getKey()])
            ->has('pets.data', 1)
            ->where('pets.data.0.id', $wanted->getKey()));
});

/**
 * A radius with no point to centre on is meaningless, and half a coordinate is
 * a client bug rather than a narrower search — answering it with the unfiltered
 * feed would look like a working search that quietly ignored the location.
 */
test('rejects half a coordinate', function (array $query, string $missing) {
    $this->from(route('home'))
        ->get(route('home', $query))
        ->assertInvalid([$missing]);
})->with([
    'latitude with no longitude' => [['latitude' => 30.0444], 'longitude'],
    'longitude with no latitude' => [['longitude' => 31.2357], 'latitude'],
]);

test('rejects a coordinate outside the world', function (array $query, string $field) {
    $this->from(route('home'))
        ->get(route('home', $query))
        ->assertInvalid([$field]);
})->with([
    'latitude past the pole' => [['latitude' => 91, 'longitude' => 31.2357], 'latitude'],
    'longitude past the antimeridian' => [['latitude' => 30.0444, 'longitude' => 181], 'longitude'],
]);

/**
 * The radius bounds the distance subquery, so an unbounded one is an unbounded
 * scan a visitor can ask for. Both ends are checked against a re-`config()`d
 * window, because a request that had stopped reading the config would still
 * agree with the defaults.
 */
test('rejects a radius outside the configured window', function (float $radius) {
    config([
        'petconnect.nearby.min_radius_km' => 5,
        'petconnect.nearby.max_radius_km' => 50,
    ]);

    $this->from(route('home'))
        ->get(route('home', ['latitude' => 30.0444, 'longitude' => 31.2357, 'radius' => $radius]))
        ->assertInvalid(['radius']);
})->with([
    'under the floor' => [4.0],
    'over the ceiling' => [51.0],
]);

/**
 * `Rule::exists` on both taxonomy filters, so the filter sheet cannot be turned
 * into a probe: an id that names no row is a validation error rather than an
 * empty feed, and an empty feed is the answer that would distinguish an id that
 * exists from one that does not.
 */
test('rejects a taxonomy id that names no row', function (string $key) {
    $this->from(route('home'))
        ->get(route('home', [$key => [99999]]))
        ->assertInvalid(["{$key}.0"]);
})->with([
    'category' => ['category_ids'],
    'breed' => ['breed_ids'],
]);

test('rejects a listing type that is not one of the offered ones', function () {
    $this->from(route('home'))
        ->get(route('home', ['listing_types' => ['barter']]))
        ->assertInvalid(['listing_types.0']);
});

/**
 * An inverted age range would return nothing at all, which reads as "no pets
 * like that" rather than "you dragged the handles past each other".
 */
test('rejects an age range that ends before it starts', function () {
    $this->from(route('home'))
        ->get(route('home', ['age_min' => 6, 'age_max' => 2]))
        ->assertInvalid(['age_max']);
});
