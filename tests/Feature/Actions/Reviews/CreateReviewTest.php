<?php

use App\Actions\Reviews\CreateReview;
use App\Enums\Reviewable;
use App\Exceptions\Reviews\AlreadyReviewed;
use App\Exceptions\Reviews\CannotReviewSelf;
use App\Exceptions\Reviews\ReviewingNotSupported;
use App\Models\Pet;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ModelReviewedNotification;
use App\Pipelines\Reviews\SubmitReview\PersistReview;
use App\Pipelines\Reviews\SubmitReview\SubmitReviewContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Notification;

test('writes the review against the resolved target and tells the person it is about', function () {
    $author = User::factory()->create();
    $subject = User::factory()->create();
    Notification::fake();

    $review = app(CreateReview::class)->handle(
        author: $author,
        reviewableType: Reviewable::User,
        reviewableId: $subject->getKey(),
        rate: 4,
        comment: 'Answered every question about the puppy.',
    );

    $this->assertDatabaseHas('reviews', [
        'id' => $review->getKey(),
        'user_id' => $author->getKey(),
        'rate' => 4,
        'comment' => 'Answered every question about the puppy.',
        'reviewable_type' => Relation::getMorphAlias(User::class),
        'reviewable_id' => $subject->getKey(),
    ]);
    Notification::assertSentTo($subject, ModelReviewedNotification::class);
    Notification::assertNotSentTo($author, ModelReviewedNotification::class);
});

/**
 * Morph columns hold aliases, never class names, so a hand-written filter
 * against `User::class` would match nothing and say nothing about it. See
 * .ai/rules/app.md.
 */
test('stamps the morph alias rather than the class name on the review', function () {
    $review = app(CreateReview::class)->handle(
        author: User::factory()->create(),
        reviewableType: Reviewable::User,
        reviewableId: User::factory()->create()->getKey(),
        rate: 5,
    );

    expect($review->fresh()->reviewable_type)->toBe('user');
});

test('cleans the comment before storing it, so a review cannot be posted around the filter', function () {
    $review = app(CreateReview::class)->handle(
        author: User::factory()->create(),
        reviewableType: Reviewable::User,
        reviewableId: User::factory()->create()->getKey(),
        rate: 2,
        comment: '  What a   bitch  ',
    );

    expect($review->fresh()->comment)->toBe('What a ****');
});

test('refuses a review of the author themselves and writes nothing', function () {
    $author = User::factory()->create();
    Notification::fake();

    expect(fn () => app(CreateReview::class)->handle(
        author: $author,
        reviewableType: Reviewable::User,
        reviewableId: $author->getKey(),
        rate: 5,
    ))->toThrow(CannotReviewSelf::class);

    $this->assertDatabaseEmpty('reviews');
    Notification::assertNothingSent();
});

test('refuses a second review of the same target by the same author and leaves one row', function () {
    $author = User::factory()->create();
    $subject = User::factory()->create();
    Review::factory()->for($author)->forUser($subject)->create();

    expect(fn () => app(CreateReview::class)->handle(
        author: $author,
        reviewableType: Reviewable::User,
        reviewableId: $subject->getKey(),
        rate: 1,
    ))->toThrow(AlreadyReviewed::class);

    expect(Review::query()->count())->toBe(1);
});

/**
 * The guard step answers before the insert, but the unique index is the
 * guarantee. Removing the row the step reads leaves the index as the only thing
 * standing between two writes, and PersistReview has to turn its refusal into
 * the same error rather than a 500 — the race two clients hit when they submit
 * at once.
 */
test('turns the unique index violation into the same refusal when the guard step is bypassed', function () {
    $author = User::factory()->create();
    $subject = User::factory()->create();
    Review::factory()->for($author)->forUser($subject)->create();

    $context = new SubmitReviewContext(
        author: $author,
        reviewableType: Reviewable::User,
        reviewableId: $subject->getKey(),
        rate: 1,
    );
    $context->setReviewable($subject);

    expect(fn () => app(PersistReview::class)
        ->handle($context, fn ($passed) => $passed))
        ->toThrow(AlreadyReviewed::class);

    expect(Review::query()->count())->toBe(1);
});

test('raises a model not found exception for a target that does not exist and writes nothing', function () {
    expect(fn () => app(CreateReview::class)->handle(
        author: User::factory()->create(),
        reviewableType: Reviewable::User,
        reviewableId: 9999,
        rate: 5,
    ))->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseEmpty('reviews');
});

/**
 * The model-side half of the whitelist.
 *
 * App\Enums\Reviewable maps a wire value to a class; App\Contracts\Reviewable is
 * the declaration that the class really is something a stranger may rate. An
 * enum case added for a model that never opted in must abort loudly rather than
 * write review rows onto something with no rating to read back.
 *
 * Driven through the context directly because the enum cannot express the
 * state: every case on it today does implement the contract, which is exactly
 * the invariant this pins.
 */
test('raises ReviewingNotSupported for a resolved target that does not implement the contract', function () {
    $context = new SubmitReviewContext(
        author: User::factory()->create(),
        reviewableType: Reviewable::User,
        reviewableId: 1,
        rate: 5,
    );
    $context->setReviewable(Pet::factory()->create());

    expect(fn () => $context->reviewableAsSubject())->toThrow(ReviewingNotSupported::class);
});
