<?php

use App\Models\Admin;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ModelReviewedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The route parameters that name a review's target.
 *
 * @return array{reviewable_type: string, reviewable_id: int}
 */
function reviewRouteParameters(User $subject): array
{
    return ['reviewable_type' => 'user', 'reviewable_id' => $subject->getKey()];
}

/**
 * A URL segment that is not a case on App\Enums\Reviewable.
 *
 * `App\Models\User` url-encoded is the exact shape the legacy application
 * accepted: `ReviewController::store` opened with
 * `$type::find($request->reviewable_id)`, a raw URL segment invoked as a static
 * call on a user-supplied class name. `pet`, `admin` and `comment` are the
 * other half of the hole — real morph aliases, so a whitelist derived from the
 * morph map instead of from the enum would let them through, and a review would
 * be written onto something with no rating to read back.
 *
 * @var array<string, list<string>>
 */
const REVIEWABLE_TYPES_OFF_THE_WHITELIST = [
    'a url encoded class name' => ['App%5CModels%5CUser'],
    'a morph alias for a listing' => ['pet'],
    'a morph alias for a moderator' => ['admin'],
    'a morph alias for a comment' => ['comment'],
    'a value on no whitelist at all' => ['dragon'],
];

/**
 * A row that really exists under the given segment, and its id.
 *
 * Load bearing, and the reason these tests do not simply post a made-up id: a
 * 404 raised because nothing of that type happens to hold that id proves
 * nothing about the whitelist. Seeding the row first means the only thing left
 * that can produce a 404 is the router refusing the type. Verified by widening
 * App\Enums\Reviewable with a `pet` case — with a real pet behind the id the
 * dataset fails, without one it stays green.
 *
 * A segment that names no morph type at all gets a user, which is the id that
 * *would* resolve if the segment were ever used as a class name.
 */
function existingTargetIdFor(string $segment): int
{
    return match ($segment) {
        'pet' => Pet::factory()->create()->getKey(),
        'admin' => Admin::factory()->create()->getKey(),
        'comment' => Comment::factory()->create()->getKey(),
        default => User::factory()->create()->getKey(),
    };
}

describe('index', function () {
    test('a guest reads the reviews written about a profile', function () {
        $subject = User::factory()->create();
        $review = Review::factory()->forUser($subject)->create(['rate' => 4, 'comment' => 'Very helpful.']);

        $this->get(route('reviews.index', reviewRouteParameters($subject)))
            ->assertOk()
            ->assertJsonPath('data.0.id', $review->getKey())
            ->assertJsonPath('data.0.rate', 4)
            ->assertJsonPath('data.0.comment', 'Very helpful.')
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    test('returns 404 for a reviewable type that is not on the whitelist', function (string $type) {
        $id = existingTargetIdFor($type);

        $this->get('/reviews/'.$type.'/'.$id)->assertNotFound();
    })->with(REVIEWABLE_TYPES_OFF_THE_WHITELIST);

    test('returns 404 for a target that does not exist', function () {
        $this->get(route('reviews.index', ['reviewable_type' => 'user', 'reviewable_id' => 9999]))
            ->assertNotFound();
    });

    test('returns 404 for a reviewable id that is not a number', function () {
        $this->get('/reviews/user/abc')->assertNotFound();
    });
});

describe('store', function () {
    test('redirects a guest to the login page and writes nothing', function () {
        $subject = User::factory()->create();

        $this->post(route('reviews.store', reviewRouteParameters($subject)), ['rate' => 5])
            ->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('reviews');
    });

    test('redirects an unverified user to the verification notice and writes nothing', function () {
        $subject = User::factory()->create();

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('reviews.store', reviewRouteParameters($subject)), ['rate' => 5])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseEmpty('reviews');
    });

    test('writes the review and notifies the person it is about', function () {
        $author = User::factory()->create();
        $subject = User::factory()->create();
        Notification::fake();

        $this->actingAs($author)
            ->from(route('home'))
            ->post(route('reviews.store', reviewRouteParameters($subject)), [
                'rate' => 5,
                'comment' => 'Answered every question about the puppy.',
            ])
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('reviews', [
            'user_id' => $author->getKey(),
            'reviewable_type' => 'user',
            'reviewable_id' => $subject->getKey(),
            'rate' => 5,
            'comment' => 'Answered every question about the puppy.',
        ]);
        Notification::assertSentTo($subject, ModelReviewedNotification::class);
    });

    /**
     * The POST half of the enum binding, and the half the legacy hole lived in:
     * the class name arrived in the URL of a write, and the controller called
     * `find()` on it. A route bound to App\Enums\Reviewable answers before any
     * middleware after SubstituteBindings, so the acting user is authenticated
     * here on purpose — a 404 that was really a redirect to the login page
     * would prove nothing about the binding.
     */
    test('returns 404 for a reviewable type that is not on the whitelist and writes nothing', function (string $type) {
        $id = existingTargetIdFor($type);

        $this->actingAs(User::factory()->create())
            ->post('/reviews/'.$type.'/'.$id, ['rate' => 5])
            ->assertNotFound();

        $this->assertDatabaseEmpty('reviews');
    })->with(REVIEWABLE_TYPES_OFF_THE_WHITELIST);

    test('returns 404 for a target that does not exist and writes nothing', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', ['reviewable_type' => 'user', 'reviewable_id' => 9999]), ['rate' => 5])
            ->assertNotFound();

        $this->assertDatabaseEmpty('reviews');
    });

    test('refuses a review of the acting user themselves on the flow level key and writes nothing', function () {
        $author = User::factory()->create();

        $this->actingAs($author)
            ->post(route('reviews.store', reviewRouteParameters($author)), ['rate' => 5])
            ->assertInvalid(['review' => 'You cannot review yourself.']);

        $this->assertDatabaseEmpty('reviews');
    });

    /**
     * A duplicate is a 422 on `review`, the flow-level key, rather than on
     * `rate` or `comment`: nothing the author could retype in either field
     * would make the submission legal.
     */
    test('refuses a second review of the same profile on the flow level key and leaves one row', function () {
        $author = User::factory()->create();
        $subject = User::factory()->create();
        Review::factory()->for($author)->forUser($subject)->create();

        $this->actingAs($author)
            ->post(route('reviews.store', reviewRouteParameters($subject)), ['rate' => 1])
            ->assertInvalid(['review' => 'You have already reviewed this.'])
            ->assertValid(['rate', 'comment']);

        expect(Review::query()->count())->toBe(1);
    });

    test('lets a different author review the same profile', function () {
        $subject = User::factory()->create();
        Review::factory()->forUser($subject)->create();

        $this->actingAs(User::factory()->create())
            ->from(route('home'))
            ->post(route('reviews.store', reviewRouteParameters($subject)), ['rate' => 3])
            ->assertValid();

        expect(Review::query()->count())->toBe(2);
    });

    test('rejects a rating outside the one to five range and writes nothing', function (int $rate) {
        $subject = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', reviewRouteParameters($subject)), ['rate' => $rate])
            ->assertInvalid(['rate']);

        $this->assertDatabaseEmpty('reviews');
    })->with([
        'below the floor' => 0,
        'above the ceiling' => 6,
        'a negative rating' => -1,
    ]);

    test('accepts a rating at each end of the range', function (int $rate) {
        $subject = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('home'))
            ->post(route('reviews.store', reviewRouteParameters($subject)), ['rate' => $rate])
            ->assertValid();

        $this->assertDatabaseHas('reviews', ['reviewable_id' => $subject->getKey(), 'rate' => $rate]);
    })->with([
        'the floor' => 1,
        'the ceiling' => 5,
    ]);

    test('rejects a review with no rating and writes nothing', function () {
        $subject = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', reviewRouteParameters($subject)), [])
            ->assertInvalid(['rate' => 'The rate field is required.']);

        $this->assertDatabaseEmpty('reviews');
    });

    test('rejects a comment longer than the configured ceiling and writes nothing', function () {
        $subject = User::factory()->create();
        $maxLength = config('petconnect.reviews.max_comment_length');

        $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', reviewRouteParameters($subject)), [
                'rate' => 5,
                'comment' => Str::repeat('a', $maxLength + 1),
            ])
            ->assertInvalid(['comment' => 'must not be greater than '.$maxLength]);

        $this->assertDatabaseEmpty('reviews');
    });

    /**
     * The store request accepts an omitted comment, because submitting a rating
     * with no words is the common case. The update request does not — see the
     * `present` rule exercised in the update block.
     */
    test('accepts a submission with no comment at all', function () {
        $subject = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('home'))
            ->post(route('reviews.store', reviewRouteParameters($subject)), ['rate' => 4])
            ->assertValid();

        $this->assertDatabaseHas('reviews', ['reviewable_id' => $subject->getKey(), 'comment' => null]);
    });

    test('cleans the submitted comment before storing it', function () {
        $subject = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('home'))
            ->post(route('reviews.store', reviewRouteParameters($subject)), [
                'rate' => 2,
                'comment' => '  What a   bitch  ',
            ])
            ->assertValid();

        expect(Review::query()->sole()->comment)->toBe('What a ****');
    });

    test('returns 429 once the acting user passes 10 reviews in a minute', function () {
        $author = User::factory()->create();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->actingAs($author)
                ->from(route('home'))
                ->post(route('reviews.store', reviewRouteParameters(User::factory()->create())), ['rate' => 5])
                ->assertRedirect();
        }

        $this->actingAs($author)
            ->post(route('reviews.store', reviewRouteParameters(User::factory()->create())), ['rate' => 5])
            ->assertTooManyRequests();

        expect(Review::query()->count())->toBe(10);
    });
});

describe('update', function () {
    test('redirects a guest to the login page and leaves the review unchanged', function () {
        $review = Review::factory()->create(['rate' => 3, 'comment' => 'Original']);

        $this->put(route('reviews.update', $review), ['rate' => 1, 'comment' => 'Edited'])
            ->assertRedirect(route('login'));

        expect($review->fresh()->rate)->toBe(3);
    });

    test('applies the edit for the author', function () {
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create(['rate' => 3, 'comment' => 'Original']);

        $this->actingAs($author)
            ->from(route('home'))
            ->put(route('reviews.update', $review), ['rate' => 1, 'comment' => 'Edited'])
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->getKey(),
            'rate' => 1,
            'comment' => 'Edited',
        ]);
    });

    /**
     * A review PUT replaces both columns, so an omitted `comment` would be a
     * silent wipe. `present` on the update request turns it into a 422 instead;
     * the store request has no such rule, because there is nothing to wipe.
     */
    test('rejects an edit that omits the comment entirely and leaves the review unchanged', function () {
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create(['rate' => 3, 'comment' => 'Original']);

        $this->actingAs($author)
            ->put(route('reviews.update', $review), ['rate' => 1])
            ->assertInvalid(['comment' => 'The comment field must be present.']);

        expect($review->fresh()->comment)->toBe('Original');
    });

    test('accepts an edit that clears the comment explicitly', function () {
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create(['rate' => 3, 'comment' => 'Original']);

        $this->actingAs($author)
            ->from(route('home'))
            ->put(route('reviews.update', $review), ['rate' => 3, 'comment' => null])
            ->assertValid();

        expect($review->fresh()->comment)->toBeNull();
    });

    test('cleans the edited comment, so a review cannot be edited around the filter', function () {
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create();

        $this->actingAs($author)
            ->from(route('home'))
            ->put(route('reviews.update', $review), ['rate' => 2, 'comment' => '  What a   bitch  '])
            ->assertValid();

        expect($review->fresh()->comment)->toBe('What a ****');
    });

    test('returns 403 for a user who did not write the review and leaves it unchanged', function () {
        $review = Review::factory()->create(['rate' => 3, 'comment' => 'Original']);

        $this->actingAs(User::factory()->create())
            ->put(route('reviews.update', $review), ['rate' => 1, 'comment' => 'Edited'])
            ->assertForbidden();

        expect($review->fresh()->comment)->toBe('Original');
    });

    test('rejects a rating outside the one to five range and leaves the review unchanged', function (int $rate) {
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create(['rate' => 3]);

        $this->actingAs($author)
            ->put(route('reviews.update', $review), ['rate' => $rate, 'comment' => null])
            ->assertInvalid(['rate']);

        expect($review->fresh()->rate)->toBe(3);
    })->with([
        'below the floor' => 0,
        'above the ceiling' => 6,
    ]);

    /**
     * A review reaches its target through a morph column, which carries no
     * foreign key, so deleting the reviewed profile strands the review and
     * nothing in the database says so. Review::resolveRouteBinding() is the
     * only thing that can speak for a target the URL never names.
     */
    test('returns 404 for a review whose target has vanished, and leaves it unchanged', function () {
        $author = User::factory()->create();
        $subject = User::factory()->create();
        $review = Review::factory()->for($author)->forUser($subject)->create(['comment' => 'Original']);
        $subject->delete();

        $this->actingAs($author)
            ->put(route('reviews.update', $review), ['rate' => 1, 'comment' => 'Edited'])
            ->assertNotFound();

        expect($review->fresh()->comment)->toBe('Original');
    });

    test('returns 404 for a review id that does not exist', function () {
        $this->actingAs(User::factory()->create())
            ->put(route('reviews.update', 9999), ['rate' => 1, 'comment' => 'Edited'])
            ->assertNotFound();
    });
});

describe('destroy', function () {
    test('redirects a guest to the login page and leaves the review in place', function () {
        $review = Review::factory()->create();

        $this->delete(route('reviews.destroy', $review))->assertRedirect(route('login'));

        $this->assertModelExists($review);
    });

    test('removes the review and the reports filed against it for its author', function () {
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create();
        $report = Report::factory()->forReportable($review)->create();

        $this->actingAs($author)
            ->from(route('home'))
            ->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('home'));

        $this->assertModelMissing($review);
        $this->assertModelMissing($report);
    });

    /**
     * Deliberately not extended to the person being reviewed: letting a subject
     * delete criticism of themselves would make the rating meaningless. Their
     * escalation path is the report flow.
     */
    test('returns 403 for the person the review is about and leaves it in place', function () {
        $subject = User::factory()->create();
        $review = Review::factory()->forUser($subject)->create();

        $this->actingAs($subject)
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertModelExists($review);
    });

    test('returns 403 for a user who did not write the review and leaves it in place', function () {
        $review = Review::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertModelExists($review);
    });

    test('returns 404 for a review whose target has vanished, and leaves it in place', function () {
        $author = User::factory()->create();
        $subject = User::factory()->create();
        $review = Review::factory()->for($author)->forUser($subject)->create();
        $subject->delete();

        $this->actingAs($author)
            ->delete(route('reviews.destroy', $review))
            ->assertNotFound();

        $this->assertModelExists($review);
    });
});
