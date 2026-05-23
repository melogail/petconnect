<?php

use App\Models\Report;
use App\Models\Review;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

it('allows an authenticated user to report a review', function () {
    $reporter = User::factory()->create();
    $review = Review::factory()->create();

    actingAs($reporter);

    $response = postJson(route('reports.store'), [
        'reportable_type' => Review::class,
        'reportable_id' => $review->id,
        'reason' => 'Spam',
        'description' => 'This review is spam.',
    ]);

    $response->assertRedirect();

    expect(Report::query()->where([
        'user_id' => $reporter->id,
        'reportable_type' => Review::class,
        'reportable_id' => $review->id,
    ])->exists())->toBeTrue();
});

it('stores the reason and description on the report', function () {
    $reporter = User::factory()->create();
    $review = Review::factory()->create();

    actingAs($reporter);

    postJson(route('reports.store'), [
        'reportable_type' => Review::class,
        'reportable_id' => $review->id,
        'reason' => 'Spam',
        'description' => 'Detailed description.',
    ]);

    $report = Report::query()->first();

    expect($report->description)->toBe('Detailed description.');
});

it('prevents guests from submitting a report', function () {
    $review = Review::factory()->create();

    $response = postJson(route('reports.store'), [
        'reportable_type' => Review::class,
        'reportable_id' => $review->id,
        'reason' => 'Spam',
    ]);

    $response->assertUnauthorized();
});

it('requires a reason when submitting a report', function () {
    $reporter = User::factory()->create();
    $review = Review::factory()->create();

    actingAs($reporter);

    $response = postJson(route('reports.store'), [
        'reportable_type' => Review::class,
        'reportable_id' => $review->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['reason']);
});

it('requires reportable_type and reportable_id when submitting a report', function () {
    $reporter = User::factory()->create();

    actingAs($reporter);

    $response = postJson(route('reports.store'), [
        'reason' => 'Spam',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['reportable_type', 'reportable_id']);
});

it('prevents reporting the same review twice', function () {
    $reporter = User::factory()->create();
    $review = Review::factory()->create();

    actingAs($reporter);

    postJson(route('reports.store'), [
        'reportable_type' => Review::class,
        'reportable_id' => $review->id,
        'reason' => 'Spam',
    ])->assertRedirect();

    $response = postJson(route('reports.store'), [
        'reportable_type' => Review::class,
        'reportable_id' => $review->id,
        'reason' => 'Hate Speech',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['reportable_id']);

    expect(Report::query()->where([
        'user_id' => $reporter->id,
        'reportable_type' => Review::class,
        'reportable_id' => $review->id,
    ])->count())->toBe(1);
});

it('marks reviews as reported by the current user on the profile page', function () {
    $profileOwner = User::factory()->create();
    $reporter = User::factory()->create();
    $review = Review::factory()->forUser($profileOwner->id)->create();

    Report::factory()->forReportable(Review::class, $review->id)->create([
        'user_id' => $reporter->id,
    ]);

    $this->actingAs($reporter)
        ->get(route('profile.show', $profileOwner))
        ->assertSuccessful()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('user.data.reviews', 1)
            ->where('user.data.reviews.0.id', $review->id)
            ->where('user.data.reviews.0.has_reported_by_current_user', true)
        );
});

it('prevents users from reporting their own review', function () {
    $author = User::factory()->create();
    $review = Review::factory()->create(['user_id' => $author->id]);

    actingAs($author);

    $response = postJson(route('reports.store'), [
        'reportable_type' => Review::class,
        'reportable_id' => $review->id,
        'reason' => 'Spam',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['reportable_id']);

    expect(Report::query()->count())->toBe(0);
});
