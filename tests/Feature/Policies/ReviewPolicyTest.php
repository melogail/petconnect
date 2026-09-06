<?php

use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * `view` is deliberately absent from this group. It had no call site anywhere
 * in the application — `reviews.index` asks `viewAny`, and `reviews.update` /
 * `reviews.destroy` ask `update` and `delete` — and ReviewPolicy's own docblock
 * asked for the method and this assertion to be deleted in the same change,
 * because a policy method nothing calls is exactly what made the legacy policy
 * misleading. The assertion goes first so the method can follow.
 */
describe('viewAny', function () {
    test('a guest may read reviews, because a profile reputation is part of its public page', function () {
        expect(Gate::forUser(null)->allows('viewAny', Review::class))->toBeTrue();
    });

    test('an unverified user may read reviews', function () {
        expect(User::factory()->unverified()->create()->can('viewAny', Review::class))->toBeTrue();
    });
});

describe('create', function () {
    test('a verified user may write a review', function () {
        expect(User::factory()->create()->can('create', Review::class))->toBeTrue();
    });

    /**
     * A review is public content about a named person and it notifies them, so
     * it needs a verified account — the same bar CommentPolicy::create sets.
     */
    test('an unverified user may not write a review', function () {
        expect(User::factory()->unverified()->create()->can('create', Review::class))->toBeFalse();
    });
});

describe('update', function () {
    test('the author may edit their own review', function () {
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create();

        expect($author->can('update', $review))->toBeTrue();
    });

    test('a stranger may not edit a review they did not write', function () {
        $review = Review::factory()->create();

        expect(User::factory()->create()->can('update', $review))->toBeFalse();
    });

    test('the person the review is about may not edit it', function () {
        $subject = User::factory()->create();
        $review = Review::factory()->forUser($subject)->create();

        expect($subject->can('update', $review))->toBeFalse();
    });

    test('an unverified author may not edit their own review', function () {
        $author = User::factory()->unverified()->create();
        $review = Review::factory()->for($author)->create();

        expect($author->can('update', $review))->toBeFalse();
    });
});

describe('delete', function () {
    test('the author may delete their own review', function () {
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create();

        expect($author->can('delete', $review))->toBeTrue();
    });

    /**
     * Deliberately not extended to the subject: letting somebody delete
     * criticism of themselves would make the rating meaningless. Their
     * escalation path is the report flow.
     */
    test('the person the review is about may not delete it', function () {
        $subject = User::factory()->create();
        $review = Review::factory()->forUser($subject)->create();

        expect($subject->can('delete', $review))->toBeFalse();
    });

    test('a stranger may not delete a review they did not write', function () {
        $review = Review::factory()->create();

        expect(User::factory()->create()->can('delete', $review))->toBeFalse();
    });

    test('an unverified author may not delete their own review', function () {
        $author = User::factory()->unverified()->create();
        $review = Review::factory()->for($author)->create();

        expect($author->can('delete', $review))->toBeFalse();
    });
});

/**
 * ReviewResource emits `can_edit` and `can_delete` by asking these two once per
 * rendered row, so a check that reached for the review's target — or for a
 * moderation role — would be one query per row on a page of ten and nothing in
 * the suite would notice: Gate calls are invisible to preventLazyLoading. See
 * .ai/rules/policies.md.
 */
test('deciding whether a review may be edited or deleted costs no query', function (string $ability) {
    $author = User::factory()->create();
    Review::factory()->for($author)->create();
    $review = Review::query()->sole();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $author->can($ability, $review);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
})->with(['update', 'delete']);
