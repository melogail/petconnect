<?php

use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use App\Models\User;
use App\Notifications\ModelLikedNotification;
use Illuminate\Support\Facades\Notification;

test('notifies the owner when their pet is liked', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $liker = User::factory()->create();
    Notification::fake();

    Like::factory()->forPet($pet)->for($liker)->create();

    Notification::assertSentTo(
        $owner,
        fn (ModelLikedNotification $notification): bool => $notification->like->likeable_id === $pet->getKey(),
    );
});

test('notifies the author when their comment is liked', function () {
    $author = User::factory()->create();
    $comment = Comment::factory()->for($author)->create();
    $liker = User::factory()->create();
    Notification::fake();

    Like::factory()->forComment($comment)->for($liker)->create();

    Notification::assertSentTo($author, ModelLikedNotification::class);
});

test('notifies the profile owner when their profile is liked', function () {
    $profileOwner = User::factory()->create();
    $liker = User::factory()->create();
    Notification::fake();

    Like::factory()->forUser($profileOwner)->for($liker)->create();

    Notification::assertSentTo($profileOwner, ModelLikedNotification::class);
});

test('sends no notification when an owner likes their own pet', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    Notification::fake();

    Like::factory()->forPet($pet)->for($owner)->create();

    Notification::assertNothingSent();
});

test('sends no notification when an author likes their own comment', function () {
    $author = User::factory()->create();
    $comment = Comment::factory()->for($author)->create();
    Notification::fake();

    Like::factory()->forComment($comment)->for($author)->create();

    Notification::assertNothingSent();
});

test('sends no notification when a user likes their own profile', function () {
    $user = User::factory()->create();
    Notification::fake();

    Like::factory()->forUser($user)->for($user)->create();

    Notification::assertNothingSent();
});
