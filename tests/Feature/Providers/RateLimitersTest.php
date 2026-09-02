<?php

use App\Models\Category;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Pet;
use App\Models\User;

/**
 * The four named rate limiters this application's own routes attach and nothing
 * else covered: `content-edits`, `inbox-actions`, `locale-switches` and
 * `pet-listing-edits`. They are declared in
 * AppServiceProvider::configureRateLimiters() and attached with `throttle:` in
 * routes/web.php, which is why the tests live under Providers rather than
 * beside one controller — no single controller owns any of them.
 *
 * Every one is exercised through the *routes*, because two halves can regress
 * independently and only the pair is the contract: the limiter can lose its
 * ceiling or its `by()` key in AppServiceProvider, and the `throttle:` string
 * can be dropped from a route in routes/web.php. A test of either half alone
 * passes while the other is broken.
 *
 * Where a limiter is shared — one bucket across several routes, which is the
 * design claim for `content-edits` and `inbox-actions` — the allowance is spent
 * across *two different routes* rather than by hammering one. Hammering one
 * route passes just as well against ten private per-route limiters, which is
 * the mistake these guard.
 *
 * `phpunit.xml` sets `CACHE_STORE=array`, so the limiter's counters live in the
 * container and start empty for every test in this file.
 */

/**
 * A conversation with one message the sender may still edit.
 *
 * Named for this file rather than reusing the `threadWithMessageFrom()` helper
 * in MessageControllerTest: helpers declared in a Pest test file are plain
 * global functions, and two files declaring the same name is a fatal redeclare
 * the moment both are loaded in one run.
 */
function limitedThread(User $sender): array
{
    $conversation = Conversation::factory()->direct()
        ->withParticipants($sender, User::factory()->create())
        ->create();

    return [
        $conversation,
        Message::factory()->for($conversation)->from($sender)->create(['content' => 'Original']),
    ];
}

/**
 * The write bag `pets.update` requires in full. A PUT replaces the listing, so
 * every group has to be present or the request never reaches the pipeline.
 */
function listingEditPayload(Category $category, string $name): array
{
    return [
        'name' => $name,
        'category_id' => $category->getKey(),
        'breed_id' => null,
        'age' => '2',
        'gender' => 'female',
        'color' => 'Black',
        'weight' => '4.2',
        'description' => 'A calm indoor cat looking for a quiet home.',
        'listing_type' => 'adoption',
        'price' => null,
        'status' => 'available',
        'location' => [
            'address' => '12 Nile Street',
            'detailedAddress' => 'Building 3, Apartment 7',
            'city' => 'Cairo',
            'state' => 'Cairo',
            'postalCode' => '11511',
            'country' => 'Egypt',
            'coordinates' => ['lat' => '30.0444', 'lng' => '31.2357'],
        ],
        'health' => [
            'status' => 'healthy',
            'vaccinated' => true,
            'spayedNeutered' => true,
            'specialNeeds' => 'None',
            'lastVetVisit' => '2024-01-15',
            'vaccinations' => [['name' => 'Rabies', 'date' => '2024-01-15']],
            'medications' => [['name' => 'Flea drops', 'usage' => 'Monthly']],
            'allergies' => ['Dust'],
            'vetName' => 'Dr. Hana',
            'vetPhone' => '+20-100-000-0000',
        ],
        'traits' => ['friendly'],
        'additionalInfo' => ['house_trained' => 'yes'],
    ];
}

describe('content-edits', function () {
    /**
     * Ten routes across five groups carry `throttle:content-edits`, and the
     * point of the family is that they share one 30-a-minute allowance rather
     * than each getting their own. Fifteen comment edits and fifteen message
     * edits is thirty, so the thirty first is refused; against a private
     * limiter per route both buckets would still be half full and it would go
     * through.
     *
     * The second caller at the end is the other half of the key. `content-edits`
     * is keyed by AppServiceProvider::rateLimitKey(), so a bucket exhausted by
     * one account must not touch anybody else's — a limiter keyed on a constant
     * would satisfy every assertion above it.
     */
    test('returns 429 on the thirty first edit in a minute, spent across two different routes', function () {
        $author = User::factory()->create();
        $comment = Comment::factory()->for($author)->create(['content' => 'Original']);
        [$conversation, $message] = limitedThread($author);

        for ($edit = 1; $edit <= 15; $edit++) {
            $this->actingAs($author)
                ->from(route('home'))
                ->put(route('comments.update', $comment), ['content' => 'Comment edit '.$edit])
                ->assertRedirect(route('home'));
        }

        for ($edit = 1; $edit <= 15; $edit++) {
            $this->actingAs($author)
                ->from(route('conversations.show', $conversation))
                ->put(route('messages.update', $message), ['content' => 'Message edit '.$edit])
                ->assertRedirect(route('conversations.show', $conversation));
        }

        $this->actingAs($author)
            ->put(route('comments.update', $comment), ['content' => 'One too many'])
            ->assertTooManyRequests();

        expect($comment->fresh()->content)->toBe('Comment edit 15')
            ->and($message->fresh()->content)->toBe('Message edit 15');

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->from(route('home'))
            ->put(route('comments.update', Comment::factory()->for($stranger)->create()), ['content' => 'Mine'])
            ->assertRedirect(route('home'));
    });
});

describe('inbox-actions', function () {
    /**
     * The inbox-wide housekeeping the client fires rather than the user, at 60
     * a minute out of one bucket. Split thirty and thirty across `read-all` and
     * `destroy-all` for the same reason the content-edits test is split: a
     * per-route limiter would leave both at half and never refuse the sixty
     * first.
     *
     * `destroy-all` is idempotent on an empty inbox, which is exactly the
     * runaway client loop the ceiling exists to end.
     */
    test('returns 429 on the sixty first inbox action in a minute, spent across two different routes', function () {
        $reader = User::factory()->create();

        for ($action = 1; $action <= 30; $action++) {
            $this->actingAs($reader)
                ->from(route('notifications.index'))
                ->post(route('notifications.read-all'))
                ->assertRedirect(route('notifications.index'));
        }

        for ($action = 1; $action <= 30; $action++) {
            $this->actingAs($reader)
                ->from(route('notifications.index'))
                ->delete(route('notifications.destroy-all'))
                ->assertRedirect(route('notifications.index'));
        }

        $this->actingAs($reader)
            ->post(route('notifications.read-all'))
            ->assertTooManyRequests();
    });
});

describe('locale-switches', function () {
    /**
     * The only unauthenticated write in the application that is not an auth
     * flow, and the only limiter in routes/web.php keyed on the IP rather than
     * on the caller — being public is the whole point of the route, so there is
     * usually no account to count against.
     *
     * Both halves of "keyed on the IP" are asserted, because each fails
     * differently. Signing in for the sixty first switch is refused: under
     * rateLimitKey() that request would key on the new user id, get an empty
     * bucket and succeed, which is how a public route silently becomes
     * unbounded to anybody willing to register. And the switch from a second
     * address succeeds: a limiter keyed on a constant would refuse it and lock
     * every visitor out of the language picker sixty switches at a time.
     */
    test('returns 429 on the sixty first language switch in a minute from one address, signed in or not', function () {
        for ($switch = 1; $switch <= 60; $switch++) {
            $this->from(route('home'))
                ->post(route('locale.update'), ['locale' => 'ar'])
                ->assertRedirect(route('home'));
        }

        $this->actingAs(User::factory()->create(['locale' => 'en']))
            ->from(route('home'))
            ->post(route('locale.update'), ['locale' => 'ar'])
            ->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);

        $this->from(route('home'))
            ->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('home'));
    });
});

describe('pet-listing-edits', function () {
    /**
     * `pets.update` accepts four images and runs two medialibrary conversions
     * on each of them synchronously, because no queue worker is deployed — so
     * the ceiling is on the CPU one owned row can be made to burn, not on how
     * many rows the caller owns. PetPolicy already bounds the second and bounds
     * nothing about the first.
     *
     * Only the minute tier is asserted. Both tiers of `pet-listing-edits` key
     * on rateLimitKey() with the prefixes Laravel needs to keep two limits in
     * one namespace apart, and 60 an hour is a ceiling on a ceiling; what
     * regresses invisibly is the `throttle:` string leaving the route.
     */
    test('returns 429 on the eleventh listing edit in a minute', function () {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $pet = Pet::factory()->for($owner)->for($category)->create(['name' => 'Original']);

        for ($edit = 1; $edit <= 10; $edit++) {
            $this->actingAs($owner)
                ->put(route('pets.update', $pet), listingEditPayload($category, 'Edit '.$edit))
                ->assertRedirect(route('pets.show', $pet));
        }

        $this->actingAs($owner)
            ->put(route('pets.update', $pet), listingEditPayload($category, 'One too many'))
            ->assertTooManyRequests();

        expect($pet->fresh()->name)->toBe('Edit 10');
    });
});
