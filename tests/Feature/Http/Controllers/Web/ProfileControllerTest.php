<?php

use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\MediaLibrary\MediaPathGenerator;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ModelLikedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * What a visit to the profile page costs end to end: the twelve queries
 * LoadProfileForDisplay issues (see PROFILE_ACTION_QUERY_COST in
 * tests/Feature/Actions/Profiles/LoadProfileForDisplayTest) plus the one the
 * router adds around them, resolving `{user}`.
 *
 * Measured through the route so the number means "the profile page", which the
 * Action's cost does not: read as the page's cost it is one query short and
 * any regression baseline drawn from it starts out wrong.
 *
 * Two listings and two reviews minimum in every fixture below:
 * `preventLazyLoading` is off on result sets of one row (.ai/rules/app.md), so a
 * one-row fixture proves nothing about the eager loads.
 */
const PROFILE_ROUTE_QUERY_COST = 13;

/**
 * Give a user the avatar ProfileResource and ReviewAuthorResource read with
 * getFirstMediaUrl().
 *
 * The owner directory is copied onto the media row exactly as UploadProfileImage
 * does it, so MediaPathGenerator never falls back to looking the owner up —
 * that fallback is a query of its own and would be counted below as if it were
 * an eager-loading miss.
 */
function attachProfilePageAvatar(User $user): void
{
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $user->media_directory_name])
        ->toMediaCollection('users');
}

/**
 * A profile with enough of everything on it that every eager load on the page is
 * observable: listings with cover photos, categories carrying their own images
 * and named breeds, and reviews by separate authors with avatars.
 */
function profileWithListingsAndReviews(int $listings = 2, int $reviews = 2): User
{
    $profile = User::factory()->create();
    attachProfilePageAvatar($profile);

    for ($index = 0; $index < $listings; $index++) {
        $category = Category::factory()->create();
        $category->addMedia(UploadedFile::fake()->image('icon.jpg'))->toMediaCollection('categories');

        $pet = Pet::factory()
            ->for($profile)
            ->for($category)
            ->for(Breed::factory()->for($category))
            ->available()
            ->create();

        $pet->addMedia(UploadedFile::fake()->image('cover.jpg'))
            ->withCustomProperties([
                Pet::FEATURED_PROPERTY => true,
                MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $profile->media_directory_name,
            ])
            ->toMediaCollection(Pet::PHOTO_COLLECTION);
    }

    for ($index = 0; $index < $reviews; $index++) {
        $author = User::factory()->create();
        attachProfilePageAvatar($author);

        Review::factory()->for($author)->forUser($profile)->create();
    }

    return $profile;
}

function countProfilePageQueries(User $profile, ?User $viewer): int
{
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    $request = $viewer === null ? test() : test()->actingAs($viewer);
    $request->get(route('profile.show', $profile))->assertOk();

    return $count;
}

/**
 * The legacy declaration was `->name('profile.show')->withoutMiddleware('auth')`
 * **inside** a `['auth', 'verified']` group: dropping `auth` while keeping
 * `verified` left EnsureEmailIsVerified running for a guest, finding no user and
 * redirecting to `verification.notice`, so the one route explicitly marked
 * public was unreachable to the public and every shared profile link bounced.
 */
test('renders the profile for a signed out visitor rather than bouncing to the verification notice', function () {
    $profile = User::factory()->create(['name' => 'Nadia Aziz']);

    $this->get(route('profile.show', $profile))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('profile/Show')
            ->where('profile.id', $profile->getKey())
            ->where('profile.name', 'Nadia Aziz')
            ->where('profile.is_self', false)
            ->where('profile.can_update', false));
});

test('renders the profile for an unverified visitor', function () {
    $profile = User::factory()->create();

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('profile.show', $profile))
        ->assertOk();
});

/**
 * A 404, not the 403 this used to assert, and the change of status is the
 * point. User::resolveRouteBinding() refuses to bind an inactive account, so
 * the page is a ModelNotFoundException before ProfileController::show can call
 * authorize() — the same answer Comment::resolveRouteBinding() gives for a
 * comment on a hidden listing, and the stronger one: a 403 confirms the account
 * exists at a guessable sequential id and a 404 does not.
 *
 * The Gate assertions are what say the policy was not gutted when the binding
 * took over. `view` is no longer what produces this status code, but it is
 * still the answer to the question asked directly, and it is what any future
 * page reaching a User through a relation rather than a URL will get. The full
 * matrix for both abilities lives in tests/Feature/Policies/UserPolicyTest.
 */
test('returns 404 for a deactivated profile, to a guest and to a signed in visitor alike', function () {
    $deactivated = User::factory()->inactive()->create();

    $this->get(route('profile.show', $deactivated))->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', $deactivated))
        ->assertNotFound();

    expect(Gate::forUser(null)->allows('view', $deactivated))->toBeFalse();
});

/**
 * `{user}` binds by id and User::getRouteKeyName() stays `id` on purpose:
 * App\Enums\Reviewable and Reportable resolve their morph targets through
 * `resolveRouteBinding()`, so a User keyed on `username` would have every one of
 * those comparisons match an integer id against a string column.
 */
test('binds the profile by id and not by username', function () {
    $profile = User::factory()->create(['username' => 'nadia-aziz']);

    expect($profile->getRouteKeyName())->toBe('id')
        ->and(route('profile.show', $profile))->toEndWith('/profile/'.$profile->getKey());

    $this->get('/profile/nadia-aziz')->assertNotFound();
});

/**
 * ReportCategory::options() and ReportReason::options() had no route anywhere in
 * the application, so the review report dialog this page hosts had no source for
 * its two select controls.
 */
test('ships the report vocabulary the review report dialog needs', function () {
    $profile = User::factory()->create();

    $this->get(route('profile.show', $profile))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reportCategories', ReportCategory::options())
            ->where('reportReasons', ReportReason::options()));
});

describe('the payload', function () {
    /**
     * The public page is reachable without an account, so the legacy resource's
     * `email`, `phone`, `address`, `lat` and `lng` published the subject's
     * contact details and exact coordinates to anybody with the link. `location`
     * is the coarse "City, State, Country" accessor and is all that is left.
     */
    test('publishes no contact detail or coordinate', function () {
        $profile = User::factory()->create([
            'city' => 'Cairo', 'state' => 'Cairo', 'country' => 'Egypt',
        ]);

        $this->get(route('profile.show', $profile))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('profile.location', 'Cairo, Cairo, Egypt')
                ->missing('profile.email')
                ->missing('profile.phone')
                ->missing('profile.address')
                ->missing('profile.lat')
                ->missing('profile.lng'));
    });

    test('summarises the reputation from subqueries rather than loaded rows', function () {
        Storage::fake(config('media-library.disk_name'));
        $profile = User::factory()->create();
        Pet::factory()->count(2)->for($profile)->create();
        Review::factory()->forUser($profile)->rating(5)->create();
        Review::factory()->forUser($profile)->rating(4)->create();

        $this->get(route('profile.show', $profile))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('profile.pets_count', 2)
                ->where('profile.reviews_count', 2)
                ->where('profile.reviews_avg_rate', 4.5));
    });

    /**
     * `category`, `breed` and `author` are emitted through `whenLoaded()`, which
     * drops the key entirely when the relation was never loaded — no exception,
     * and a query count that goes **down**. Asserting the keys are present is
     * the only guard for that half of the regression (.ai/rules/resources.md).
     */
    test('carries the eager loaded keys the cards and the reviews render', function () {
        Storage::fake(config('media-library.disk_name'));
        $profile = profileWithListingsAndReviews();

        $this->get(route('profile.show', $profile))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('listings.data', 2)
                ->has('reviews.data', 2)
                ->has('listings.data.0.category.id')
                ->has('listings.data.0.breed.id')
                ->has('reviews.data.0.author.id'));
    });

    /**
     * Every card on this page has the same owner — the profile — so the listings
     * query deliberately does not eager load `user`, and `whenLoaded('user')`
     * leaves the key out. This is the one page where that is true, so it is
     * pinned rather than left to look like the miss above.
     */
    test('omits the listing owner, because every card on this page has the same one', function () {
        Storage::fake(config('media-library.disk_name'));
        $profile = profileWithListingsAndReviews();

        $this->get(route('profile.show', $profile))
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('listings.data.0.user'));
    });

    test('pages the listings and the reviews independently', function () {
        Storage::fake(config('media-library.disk_name'));
        config(['petconnect.profiles.listings_per_page' => 1, 'petconnect.profiles.reviews_per_page' => 1]);
        $profile = profileWithListingsAndReviews();

        $this->get(route('profile.show', ['user' => $profile, 'listings' => 2]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('listings.meta.current_page', 2)
                ->where('reviews.meta.current_page', 1));
    });

    /**
     * Nine a page by decision (2026-09-06): three rows of the visitor's
     * three-column grid. Asserted against the default rather than a
     * re-`config()`d value because the number itself is the requirement.
     */
    test('pages the listings nine at a time', function () {
        Storage::fake(config('media-library.disk_name'));
        $profile = User::factory()->create();
        Pet::factory()->count(10)->for($profile)->create();

        $this->get(route('profile.show', $profile))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('listings.data', 9)
                ->where('listings.meta.per_page', 9)
                ->where('listings.meta.last_page', 2));
    });

    /**
     * `is_self` is what `pages/profile/Show.vue` branches on: the owner sees
     * their listings as a table with edit, remove and availability controls;
     * everybody else sees the card grid. The guest arm is pinned at the top of
     * this file; this is the other one.
     */
    test('tells the owner the profile is their own', function () {
        $profile = User::factory()->create();

        $this->actingAs($profile)
            ->get(route('profile.show', $profile))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('profile.is_self', true));
    });

    /**
     * The owner's table has a "mark as available" control on an unavailable
     * row, which is only reachable if that row is still on the page.
     */
    test('keeps an unavailable listing on the page so its owner can reactivate it', function () {
        Storage::fake(config('media-library.disk_name'));
        $profile = User::factory()->create();
        $pet = Pet::factory()->for($profile)->unavailable()->create();

        $this->actingAs($profile)
            ->get(route('profile.show', $profile))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('listings.data.0.id', $pet->getKey())
                ->where('listings.data.0.status', 'unavailable'));
    });

    test("carries the view count the owner's table renders", function () {
        Storage::fake(config('media-library.disk_name'));
        $profile = User::factory()->create();
        Pet::factory()->for($profile)->create(['views' => 7]);

        $this->actingAs($profile)
            ->get(route('profile.show', $profile))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('listings.data.0.views', 7));
    });
});

/**
 * The star widget cannot draw a scale it has not been told the length of, and
 * was hardcoding five. The bounds are read through the same
 * ReviewValidationRules accessors the `min:`/`max:` rules are built from, so
 * `petconnect.reviews.max_rate` cannot move for the validator without moving
 * for the widget — which is the promise the config file makes and could not
 * keep until this prop existed. A non-default value is what tells the two
 * apart; a hardcoded prop passes on the defaults.
 */
test('ships the rating scale the star widget draws itself from, and enforces the same one', function () {
    config([
        'petconnect.reviews.min_rate' => 1,
        'petconnect.reviews.max_rate' => 10,
        'petconnect.reviews.max_comment_length' => 300,
    ]);
    $profile = User::factory()->create();

    $this->get(route('profile.show', $profile))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviewBounds', [
                'min_rate' => 1,
                'max_rate' => 10,
                'max_comment_length' => 300,
            ]));

    $this->actingAs(User::factory()->create())
        ->from(route('profile.show', $profile))
        ->post(route('reviews.store', ['reviewable_type' => 'user', 'reviewable_id' => $profile->getKey()]), [
            'rate' => 11,
            'comment' => 'Too high for the scale this page publishes.',
        ])
        ->assertInvalid(['rate']);
});

/**
 * A second review of the same person by the same author is refused by a unique
 * index and by SubmitReview\EnsureNotAlreadyReviewed, and until this flag the
 * page could not tell: it offered the form to everybody and explained
 * afterwards through `errors.review`.
 *
 * It is a `withExists` subquery on the row LoadProfileForDisplay was already
 * fetching, so it costs no round trip — the flat count in `the query budget`
 * below is measured for a guest, and the signed-in case is measured with it.
 */
test('tells a viewer who has already reviewed this profile that they have', function () {
    $profile = User::factory()->create();
    $author = User::factory()->create();
    Review::factory()->for($author)->forUser($profile)->create();

    $this->actingAs($author)
        ->get(route('profile.show', $profile))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('profile.has_reviewed', true));
});

test('reports no review from a viewer who has written none, and none from a guest', function () {
    $profile = User::factory()->create();
    Review::factory()->forUser($profile)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', $profile))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('profile.has_reviewed', false));

    $this->get(route('profile.show', $profile))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('profile.has_reviewed', false));
});

/**
 * `last_seen_at` was emitted here, read by nobody, and **written by no code
 * path in the application** — only UserFactory and UserSeeder set it, so in
 * production every profile published a null and in a seeded environment a
 * fabricated one. A public "last seen" is a presence disclosure that needs its
 * own decision rather than a column leaking onto a payload, so the key is off
 * and this is what keeps it off. The column stays.
 */
test('publishes no presence timestamp', function () {
    $profile = User::factory()->create(['last_seen_at' => now()]);

    $this->get(route('profile.show', $profile))
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('profile.last_seen_at'));
});

/**
 * The write half of this page, and the one that was missing. App\Models\User
 * has implemented App\Contracts\Likeable since the like vertical landed, but no
 * route ever reached it, so ProfileResource emitted an `is_liked` flag nothing
 * in the application could flip.
 */
describe('like', function () {
    test('redirects a guest to the login page and records no like', function () {
        $profile = User::factory()->create();

        $this->post(route('profile.like', $profile))->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('likes');
    });

    test('redirects an unverified user to the verification notice and records no like', function () {
        $profile = User::factory()->create();

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('profile.like', $profile))
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseEmpty('likes');
    });

    /**
     * One toggle route rather than a like and an unlike, so a client cannot ask
     * for a transition that has already happened — the shape `pets.like`,
     * `comments.like` and `pets.status.toggle` all use.
     */
    test('records a like and takes it away again on a second press', function () {
        $liker = User::factory()->create();
        $profile = User::factory()->create();

        $this->actingAs($liker)
            ->from(route('profile.show', $profile))
            ->post(route('profile.like', $profile))
            ->assertRedirect(route('profile.show', $profile));

        $this->assertDatabaseHas('likes', [
            'user_id' => $liker->getKey(),
            'likeable_type' => 'user',
            'likeable_id' => $profile->getKey(),
        ]);

        $this->actingAs($liker)->post(route('profile.like', $profile))->assertRedirect();

        $this->assertDatabaseEmpty('likes');
    });

    test('shows the like back to the viewer who left it', function () {
        $liker = User::factory()->create();
        $profile = User::factory()->create();

        $this->actingAs($liker)->post(route('profile.like', $profile))->assertRedirect();

        $this->actingAs($liker)
            ->get(route('profile.show', $profile))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('profile.is_liked', true));
    });

    /**
     * A 404 rather than the 403 this used to assert, for the same reason the
     * page above is: User::resolveRouteBinding() will not bind an inactive
     * account, so this route never reaches UserPolicy::like. The write half is
     * unchanged — no row either way — and the Gate assertion pins that the
     * policy still refuses when asked directly, which is what keeps this
     * profile unlikeable through any caller that reaches a User without a URL.
     */
    test('returns 404 for a deactivated profile and records no like', function () {
        $deactivated = User::factory()->inactive()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('profile.like', $deactivated))
            ->assertNotFound();

        $this->assertDatabaseEmpty('likes');

        expect(User::factory()->create()->can('like', $deactivated))->toBeFalse();
    });

    /**
     * The like notifies the person it is about, which is the reason the route
     * is verified-only. LikeObserver is what sends it; this is the one case
     * that proves the route reaches it through Actions\Likes\ToggleLike.
     */
    test('notifies the profile owner that they were liked', function () {
        Notification::fake();
        $liker = User::factory()->create();
        $profile = User::factory()->create();

        $this->actingAs($liker)->post(route('profile.like', $profile))->assertRedirect();

        Notification::assertSentTo(
            $profile,
            fn (ModelLikedNotification $notification): bool => $notification->like->likeable_id === $profile->getKey()
                && $notification->like->user_id === $liker->getKey(),
        );
    });

    test('sends no notification when a user likes their own profile', function () {
        Notification::fake();
        $liker = User::factory()->create();

        $this->actingAs($liker)->post(route('profile.like', $liker))->assertRedirect();

        Notification::assertNothingSent();
    });

    /**
     * `profile-likes` is its own limiter rather than shared with `pet-likes`
     * and `comment-likes`, so a spree in one place cannot eat a visitor's
     * allowance in another. Same budget: 30 a minute.
     */
    test('returns 429 once the acting user passes 30 profile likes in a minute', function () {
        $liker = User::factory()->create();
        $profile = User::factory()->create();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->actingAs($liker)->post(route('profile.like', $profile))->assertRedirect();
        }

        $this->actingAs($liker)->post(route('profile.like', $profile))->assertTooManyRequests();
    });
});

describe('the query budget', function () {
    test('renders for a guest at the recorded page query cost', function () {
        Storage::fake(config('media-library.disk_name'));
        $profile = profileWithListingsAndReviews();

        expect(countProfilePageQueries($profile, null))->toBe(PROFILE_ROUTE_QUERY_COST);
    });

    test('does not grow when the profile holds more listings and more reviews', function () {
        Storage::fake(config('media-library.disk_name'));
        $small = profileWithListingsAndReviews(2, 2);
        $large = profileWithListingsAndReviews(6, 6);

        expect(countProfilePageQueries($large, null))->toBe(countProfilePageQueries($small, null));
    });
});
