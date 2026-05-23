<?php

use App\Enums\PetStatus;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Report;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('includes pet comments on the home page', function () {
    $pet = Pet::factory()->create(['status' => PetStatus::available]);
    $author = User::factory()->create();

    $comment = Comment::factory()->create([
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
        'user_id' => $author->id,
        'content' => 'What a lovely pet!',
    ]);

    Comment::factory()->create([
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
        'user_id' => $author->id,
        'parent_id' => $comment->id,
        'content' => 'Thanks for asking!',
    ]);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('reportReasons')
            ->has('pets.data', 1)
            ->where('pets.data.0.id', $pet->id)
            ->where('pets.data.0.commentsCount', 2)
            ->has('pets.data.0.comments', 1)
            ->where('pets.data.0.comments.0.id', $comment->id)
            ->where('pets.data.0.comments.0.content', 'What a lovely pet!')
            ->has('pets.data.0.comments.0.replies', 1)
            ->where('pets.data.0.comments.0.replies.0.content', 'Thanks for asking!')
        );
});

it('allows a verified user to comment from the home page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $pet = Pet::factory()->create(['status' => PetStatus::available]);

    $this->actingAs($user)
        ->from(route('home'))
        ->post(route('comments.store', ['commentable_type' => 'pet', 'commentable_id' => $pet->id]), [
            'content' => 'Interested in adopting!',
        ])
        ->assertRedirect(route('home'))
        ->assertSessionHas('success');

    expect(Comment::query()->where('commentable_id', $pet->id)->count())->toBe(1);
});

it('marks comments as reported by the current user on the home page', function () {
    $reporter = User::factory()->create();
    $pet = Pet::factory()->create(['status' => PetStatus::available]);
    $comment = Comment::factory()->create([
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
    ]);

    Report::factory()->forReportable(Comment::class, $comment->id)->create([
        'user_id' => $reporter->id,
    ]);

    $this->actingAs($reporter)
        ->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pets.data.0.comments.0.id', $comment->id)
            ->where('pets.data.0.comments.0.has_reported_by_current_user', true)
        );
});

it('prevents reporting the same comment twice', function () {
    $reporter = User::factory()->create();
    $comment = Comment::factory()->create([
        'commentable_type' => Pet::class,
        'commentable_id' => Pet::factory()->create(['status' => PetStatus::available])->id,
    ]);

    actingAs($reporter);

    $this->post(route('reports.store'), [
        'reportable_type' => Comment::class,
        'reportable_id' => $comment->id,
        'reason' => 'Spam',
    ])->assertRedirect();

    $response = $this->post(route('reports.store'), [
        'reportable_type' => Comment::class,
        'reportable_id' => $comment->id,
        'reason' => 'Spam',
    ]);

    $response->assertSessionHasErrors(['reportable_id']);

    expect(Report::query()->where([
        'user_id' => $reporter->id,
        'reportable_type' => Comment::class,
        'reportable_id' => $comment->id,
    ])->count())->toBe(1);
});

it('prevents users from reporting their own comment', function () {
    $author = User::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $author->id,
        'commentable_type' => Pet::class,
        'commentable_id' => Pet::factory()->create(['status' => PetStatus::available])->id,
    ]);

    actingAs($author);

    $response = $this->post(route('reports.store'), [
        'reportable_type' => Comment::class,
        'reportable_id' => $comment->id,
        'reason' => 'Spam',
    ]);

    $response->assertSessionHasErrors(['reportable_id']);

    expect(Report::query()->count())->toBe(0);
});
