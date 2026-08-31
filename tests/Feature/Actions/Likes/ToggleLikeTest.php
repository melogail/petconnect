<?php

use App\Actions\Likes\ToggleLike;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use App\Notifications\ModelLikedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * One like path for every likeable model: this Action replaced
 * Actions\Pets\TogglePetLike when comments became the second caller, so both
 * models are exercised through it.
 *
 * Who gets told about a like is App\Observers\LikeObserver's decision and its
 * full matrix lives in tests/Feature/Observers/LikeObserverTest.php; the cases
 * here are what proves this Action reaches that path at all.
 */
test('records a like for a pet and notifies its owner', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $liker = User::factory()->create();
    Notification::fake();

    $liked = app(ToggleLike::class)->handle($pet, $liker);

    expect($liked)->toBeTrue();
    $this->assertDatabaseHas('likes', [
        'user_id' => $liker->getKey(),
        'likeable_type' => 'pet',
        'likeable_id' => $pet->getKey(),
    ]);
    Notification::assertSentTo($owner, ModelLikedNotification::class);
});

test('records a like for a comment and notifies its author', function () {
    $author = User::factory()->create();
    $comment = Comment::factory()->for($author)->create();
    $liker = User::factory()->create();
    Notification::fake();

    $liked = app(ToggleLike::class)->handle($comment, $liker);

    expect($liked)->toBeTrue();
    $this->assertDatabaseHas('likes', [
        'user_id' => $liker->getKey(),
        'likeable_type' => 'comment',
        'likeable_id' => $comment->getKey(),
    ]);
    Notification::assertSentTo($author, ModelLikedNotification::class);
});

test('removes the like when the same user toggles a second time', function () {
    $comment = Comment::factory()->create();
    $liker = User::factory()->create();
    app(ToggleLike::class)->handle($comment, $liker);

    $liked = app(ToggleLike::class)->handle($comment, $liker);

    expect($liked)->toBeFalse();
    $this->assertDatabaseEmpty('likes');
});

test('sends no notification when an author likes their own comment', function () {
    $author = User::factory()->create();
    $comment = Comment::factory()->for($author)->create();
    Notification::fake();

    app(ToggleLike::class)->handle($comment, $author);

    Notification::assertNothingSent();
    $this->assertDatabaseHas('likes', [
        'user_id' => $author->getKey(),
        'likeable_id' => $comment->getKey(),
    ]);
});
