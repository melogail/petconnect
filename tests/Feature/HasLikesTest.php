<?php

use App\Enums\PetStatus;
use App\Models\Like;
use App\Models\Pet;
use App\Models\User;
use App\Notifications\ModelLikedNotification;
use Illuminate\Support\Facades\Notification;

it('allows an authenticated user to like a pet', function () {
    $user = User::factory()->create();
    $pet = Pet::factory()->create(['status' => PetStatus::available]);

    $this->actingAs($user);

    $like = $pet->makeLike();

    expect($like)->toBeInstanceOf(Like::class)
        ->and($like->user_id)->toBe($user->id)
        ->and($like->likeable_type)->toBe(Pet::class)
        ->and($like->likeable_id)->toBe($pet->id);

    $this->assertDatabaseHas('likes', [
        'user_id' => $user->id,
        'likeable_type' => Pet::class,
        'likeable_id' => $pet->id,
    ]);
});

it('does not create duplicate likes for the same user and pet', function () {
    $user = User::factory()->create();
    $pet = Pet::factory()->create(['status' => PetStatus::available]);

    $this->actingAs($user);

    $first = $pet->makeLike();
    $second = $pet->makeLike();

    expect($first->id)->toBe($second->id)
        ->and(Like::query()->where('user_id', $user->id)->whereMorphedTo('likeable', $pet)->count())->toBe(1);
});

it('allows an authenticated user to remove a like from a pet', function () {
    $user = User::factory()->create();
    $pet = Pet::factory()->create(['status' => PetStatus::available]);

    $this->actingAs($user);

    $pet->makeLike();
    expect($pet->removeLike())->toBeTrue();

    $this->assertDatabaseMissing('likes', [
        'user_id' => $user->id,
        'likeable_type' => Pet::class,
        'likeable_id' => $pet->id,
    ]);
});

it('allows an authenticated user to like another user profile', function () {
    $liker = User::factory()->create();
    $profile = User::factory()->create();

    $this->actingAs($liker);

    $like = $profile->makeLike();

    expect($like)->toBeInstanceOf(Like::class)
        ->and($like->user_id)->toBe($liker->id)
        ->and($like->likeable_type)->toBe(User::class)
        ->and($like->likeable_id)->toBe($profile->id)
        ->and($profile->isLikedBy($liker))->toBeTrue();

    $this->assertDatabaseHas('likes', [
        'user_id' => $liker->id,
        'likeable_type' => User::class,
        'likeable_id' => $profile->id,
    ]);
});

it('does not create duplicate likes for the same user and profile', function () {
    $liker = User::factory()->create();
    $profile = User::factory()->create();

    $this->actingAs($liker);

    $first = $profile->makeLike();
    $second = $profile->makeLike();

    expect($first->id)->toBe($second->id)
        ->and(Like::query()->where('user_id', $liker->id)->whereMorphedTo('likeable', $profile)->count())->toBe(1);
});

it('notifies the pet owner when their pet is liked', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $liker = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'status' => PetStatus::available,
    ]);

    $this->actingAs($liker);

    $like = $pet->makeLike();

    Notification::assertSentTo($owner, ModelLikedNotification::class, function (ModelLikedNotification $notification) use ($like, $liker, $pet) {
        $payload = $notification->toArray($liker);

        return $notification->like->is($like)
            && $payload['like_id'] === $like->id
            && $payload['liker_id'] === $liker->id
            && $payload['likeable_type'] === Pet::class
            && $payload['likeable_id'] === $pet->id
            && $payload['type'] === 'like'
            && $payload['message'] === __('notifications.liked_pet', [
                'name' => $liker->name,
                'pet' => $pet->name,
            ]);
    });

    Notification::assertNotSentTo($liker, ModelLikedNotification::class);
});

it('does not notify the pet owner when they like their own pet', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'status' => PetStatus::available,
    ]);

    $this->actingAs($owner);

    $pet->makeLike();

    Notification::assertNothingSent();
});

it('notifies the profile owner when their profile is liked', function () {
    Notification::fake();

    $profile = User::factory()->create();
    $liker = User::factory()->create();

    $this->actingAs($liker);

    $like = $profile->makeLike();

    Notification::assertSentTo($profile, ModelLikedNotification::class, function (ModelLikedNotification $notification) use ($like, $liker, $profile) {
        $payload = $notification->toArray($profile);

        return $notification->like->is($like)
            && $payload['like_id'] === $like->id
            && $payload['liker_id'] === $liker->id
            && $payload['likeable_type'] === User::class
            && $payload['likeable_id'] === $profile->id
            && $payload['type'] === 'like'
            && $payload['message'] === __('notifications.liked_profile', [
                'name' => $liker->name,
            ]);
    });

    Notification::assertNotSentTo($liker, ModelLikedNotification::class);
});

it('does not notify a user when they like their own profile', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user);

    $user->makeLike();

    Notification::assertNothingSent();
});
