<?php

use App\Enums\ListingType;
use App\Http\Middleware\HandleInertiaRequests;
use App\MediaLibrary\MediaPathGenerator;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
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

/**
 * Ask for the feed the way the client asks for it — as an Inertia visit — so the
 * answer is the JSON page object and its merge bookkeeping (`mergeProps`) rather
 * than the HTML shell, which carries neither.
 *
 * The version header has to match or Inertia answers 409 with an
 * `X-Inertia-Location` and never renders the page at all, so it is computed the
 * same way the middleware computes it. `Inertia::getVersion()` is not that value
 * outside a request — reaching for it here produced the 409, measured.
 *
 * @param  array<string, string>  $headers
 * @param  array<string, mixed>  $query
 */
function visitFeedAsInertia(TestCase $test, array $headers = [], array $query = []): TestResponse
{
    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? '',
        ...$headers,
    ])->get(route('home', $query));
}

/**
 * The three headers `@inertiajs/core` writes for a partial visit, built the way
 * it builds them.
 *
 * `X-Inertia-Partial-Data` is `only.concat(reset)` — literally, so a prop named
 * in both is sent twice, and that duplicate is reproduced here rather than
 * tidied away, because it is what the browser puts on the wire.
 *
 * The consequence that matters: a non-empty `reset` makes the concatenation
 * non-empty, so `reset: ['pets']` with **no** `only` still goes out as a
 * partial visit asking for `pets` alone. Sending `X-Inertia-Reset` on its own,
 * with no `X-Inertia-Partial-Component` and no `X-Inertia-Partial-Data` — which
 * is what these tests did until the bug below was found — is not that request.
 * It is not partial at all, and the server answers it with the full prop set,
 * so it agrees with a client that has stopped receiving half its props.
 * Measured: reset header alone returns 12 props; the shape the client actually
 * sends returned `errors, pets`.
 *
 * @param  list<string>  $only
 * @param  list<string>  $reset
 * @return array<string, string>
 */
function feedPartialHeaders(array $only, array $reset): array
{
    return [
        'X-Inertia-Partial-Component' => 'Home',
        'X-Inertia-Partial-Data' => implode(',', [...$only, ...$reset]),
        'X-Inertia-Reset' => implode(',', $reset),
    ];
}

/**
 * These three pin **the server-side contract the filter sheet depends on**, and
 * deliberately not the stale-cards symptom itself.
 *
 * The symptom — filter from a scrolled-down feed and the previous query's cards
 * stay above the new ones — lives entirely in the browser: `pets` ships through
 * `Inertia::scroll()`, the client accumulates `pets.data` across visits, and a
 * visit that does not ask for a reset merges the new query's page 1 into the old
 * query's list. The fix is a `reset: ['pets']` option on the component's
 * `router.get`. Nothing on the server changes, so no test here can fail before
 * that fix and pass after it, and one written to look as though it does would be
 * claiming coverage of a bug it cannot see.
 *
 * What these do buy: the client-side fix is only a fix while the server keeps
 * honouring `X-Inertia-Reset` on this route and keeps declaring `pets.data` as
 * something to merge. If either changes, the sheet silently goes back to
 * appending and nothing else in the suite notices. That is what is pinned.
 *
 * Two listings, not one, because `Model::preventLazyLoading()` is disabled
 * outright on result sets of 0 or 1 row (.ai/rules/app.md) — a single-row fixture
 * turns the guard off for the request under test.
 *
 * The visual symptom itself is a **named, uncovered gap**: it needs a real
 * browser, this project installs no browser-test package, and adding one is a
 * dependency change.
 */
test('the feed declares pets.data a merge prop, which is what makes a scroll visit append', function () {
    Pet::factory()->available()->count(2)->create();

    visitFeedAsInertia($this)
        ->assertOk()
        ->assertJsonPath('mergeProps', ['pets.data'])
        ->assertJsonCount(2, 'props.pets.data');
});

/**
 * `scrollProps.pets.reset` is the second half of what the reset buys and is
 * asserted beside the withdrawn `mergeProps`: the first tells the client not to
 * concatenate this payload onto the list it holds, the second tells
 * `<InfiniteScroll>` to drop the page bookkeeping it accumulated for the
 * previous query. Measured, a partial visit with no reset carries
 * `mergeProps: ['pets.data']` and `scrollProps.pets.reset: false`, so neither
 * assertion is true of every response.
 */
test('a visit carrying X-Inertia-Reset for pets withdraws that merge declaration', function () {
    Pet::factory()->available()->count(2)->create();

    visitFeedAsInertia($this, feedPartialHeaders(['pets', 'filters'], ['pets']))
        ->assertOk()
        ->assertJsonMissingPath('mergeProps')
        ->assertJsonPath('scrollProps.pets.reset', true)
        ->assertJsonCount(2, 'props.pets.data');
});

/**
 * The prop is registered for merging under its dotted path, `pets.data`, but the
 * reset is matched on the **prop name**, `pets`. Resetting `pets.data` is not a
 * near-miss that half works — it is a silent no-op that leaves the merge in
 * place and the stale cards on screen, and it is the plausible typo for anyone
 * reading `mergeProps` and copying what they see there.
 */
test('resetting the dotted merge path instead of the prop name leaves the merge in place', function () {
    Pet::factory()->available()->count(2)->create();

    visitFeedAsInertia($this, feedPartialHeaders(['pets', 'filters'], ['pets.data']))
        ->assertOk()
        ->assertJsonPath('mergeProps', ['pets.data'])
        ->assertJsonPath('scrollProps.pets.reset', false);
});

/**
 * The mechanism that made the three tests below necessary, stated on its own.
 *
 * A partial response is exactly the props the visit named, plus `errors`. The
 * server does **not** top it up with the rest of the page, and the client
 * merges what arrives over the props it is already holding — so any prop the
 * calling component reads and does not name stays frozen at whatever the last
 * full visit put there.
 *
 * That is not hypothetical. `reset: ['pets']` with no `only` produces this
 * exact request, and it shipped: the nearby chip cleared the coordinates from
 * the URL and left the heading reading "Nearby Pets" because `nearby` never
 * came back, and the filter sheet cleared the feed and reopened with every box
 * still ticked because `filters` never came back.
 *
 * The whole key set is asserted, not just the absences, because what is being
 * pinned is the prop set being *closed*. `assertInertia()` is not available for
 * any of this: AssertableInertia reads `viewData('page')` and so only ever sees
 * the HTML shell, and an Inertia visit does not render one.
 */
test('a partial feed visit naming pets alone comes back without filters, nearby or radius', function () {
    Pet::factory()->available()->count(2)->create();

    $response = visitFeedAsInertia($this, feedPartialHeaders([], ['pets']))
        ->assertOk()
        ->assertJsonCount(2, 'props.pets.data');

    expect(array_keys($response->json('props')))->toEqualCanonicalizing(['errors', 'pets']);
});

/**
 * Somewhere for the nearby cases below to search around. The listings are put
 * on this exact point rather than left where PetFactory's city list dropped
 * them, so both dataset cases return the same two cards and the only thing that
 * differs between them is `nearby` and `radius` — which is what the test is
 * about. Left to the factory's geography, the nearby case would return however
 * many rows happened to land inside 25.5 km.
 */
const CAIRO_LATITUDE = 30.0444;

const CAIRO_LONGITUDE = 31.2357;

/**
 * NearbySearchButton's chip and Home.vue's geolocation redirect, which send the
 * same `only: ['pets', 'nearby', 'radius']` + `reset: ['pets']` pair and differ
 * only in what they leave in the query string.
 *
 * `nearby` decides the heading and whether the chip renders at all, and
 * `radius` is the number inside it — so a response that omits them leaves the
 * page describing the search the visitor just cancelled, or fails to describe
 * the one they just started. Both are asserted as values rather than as
 * present keys: the whole failure was the client holding a *stale* `true`.
 */
test('a partial feed visit from the nearby chip comes back with the nearby and radius its URL now describes', function (array $query, bool $nearby, ?float $radius) {
    Pet::factory()->available()->at(CAIRO_LATITUDE, CAIRO_LONGITUDE)->count(2)->create();

    $response = visitFeedAsInertia($this, feedPartialHeaders(['pets', 'nearby', 'radius'], ['pets']), $query)
        ->assertOk()
        ->assertJsonCount(2, 'props.pets.data')
        ->assertJsonPath('props.nearby', $nearby)
        ->assertJsonPath('props.radius', $radius);

    expect(array_keys($response->json('props')))
        ->toEqualCanonicalizing(['errors', 'pets', 'nearby', 'radius']);
})->with([
    'the chip clearing the coordinates' => [[], false, null],
    'the geolocation redirect supplying them' => [
        ['latitude' => CAIRO_LATITUDE, 'longitude' => CAIRO_LONGITUDE, 'radius' => 25.5],
        true,
        25.5,
    ],
]);

/**
 * PetFilterSheet's apply, `only: ['pets', 'filters']` + `reset: ['pets']`.
 *
 * The sheet holds no selection of its own between openings — it rebuilds its
 * draft from the `filters` prop — so this response is the only record of what
 * the visitor picked. Without it the feed narrowed and the sheet reopened
 * showing the *previous* query's boxes ticked, which is the one state from
 * which no further filter can be applied correctly.
 *
 * The feed is asserted alongside it because agreement is the contract: the
 * cards that came back and the boxes the sheet will re-tick have to describe
 * the same query.
 */
test('a partial feed visit from the filter sheet comes back with the filters it reopens from', function () {
    $category = Category::factory()->create();
    $wanted = Pet::factory()->available()->for($category)->create(['vaccinated' => true]);
    Pet::factory()->available()->count(2)->create(['vaccinated' => false]);

    $response = visitFeedAsInertia(
        $this,
        feedPartialHeaders(['pets', 'filters'], ['pets']),
        ['category_ids' => [$category->getKey()], 'vaccinated' => '1'],
    )
        ->assertOk()
        ->assertJsonCount(1, 'props.pets.data')
        ->assertJsonPath('props.pets.data.0.id', $wanted->getKey())
        ->assertJsonPath('props.filters', [
            'category_ids' => [$category->getKey()],
            'breed_ids' => [],
            'age_min' => null,
            'age_max' => null,
            'listing_types' => [],
            'vaccinated' => true,
        ]);

    expect(array_keys($response->json('props')))->toEqualCanonicalizing(['errors', 'pets', 'filters']);
});
