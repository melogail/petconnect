<?php

use App\Enums\PetStatus;
use App\Models\Pet;
use App\Models\User;
use App\Notifications\ModelLikedNotification;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
});

it('shares notifications with authenticated inertia requests', function () {
    $owner = User::factory()->create();
    $liker = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'status' => PetStatus::available,
        'name' => 'Buddy',
    ]);

    $this->actingAs($liker);
    $pet->makeLike();

    $this->actingAs($owner)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications')
            ->where('notifications.unread_count', 1)
            ->has('notifications.items', 1)
            ->where('notifications.items.0.read', false)
            ->where('notifications.items.0.type', 'like')
            ->where('notifications.items.0.text', __('notifications.liked_pet', [
                'name' => $liker->name,
                'pet' => 'Buddy',
            ]))
        );
});

it('marks a single notification as read', function () {
    $owner = User::factory()->create();
    $liker = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'status' => PetStatus::available,
    ]);

    $this->actingAs($liker);
    $pet->makeLike();

    $notification = $owner->fresh()->notifications()->first();

    $this->actingAs($owner)
        ->from(route('home'))
        ->post(route('notifications.read', $notification))
        ->assertRedirect(route('home'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $owner = User::factory()->create();
    $liker = User::factory()->create();
    $petA = Pet::factory()->create([
        'user_id' => $owner->id,
        'status' => PetStatus::available,
    ]);
    $petB = Pet::factory()->create([
        'user_id' => $owner->id,
        'status' => PetStatus::available,
    ]);

    $this->actingAs($liker);
    $petA->makeLike();
    $petB->makeLike();

    expect($owner->fresh()->unreadNotifications()->count())->toBe(2);

    $this->actingAs($owner)
        ->from(route('home'))
        ->post(route('notifications.read-all'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('success');

    expect($owner->fresh()->unreadNotifications()->count())->toBe(0);
});

it('deletes all notifications', function () {
    $owner = User::factory()->create();
    $liker = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'status' => PetStatus::available,
    ]);

    $this->actingAs($liker);
    $pet->makeLike();

    expect($owner->fresh()->notifications()->count())->toBe(1);

    $this->actingAs($owner)
        ->from(route('home'))
        ->delete(route('notifications.destroy-all'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('success');

    expect($owner->fresh()->notifications()->count())->toBe(0);
});

it('prevents marking another users notification as read', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $liker = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'status' => PetStatus::available,
    ]);

    $this->actingAs($liker);
    $pet->makeLike();

    $notification = $owner->fresh()->notifications()->first();

    $this->actingAs($stranger)
        ->post(route('notifications.read', $notification))
        ->assertNotFound();
});

it('redirects guests from notification actions', function () {
    $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    $this->delete(route('notifications.destroy-all'))->assertRedirect(route('login'));
});

it('builds liked notification payload with message and url', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $liker = User::factory()->create(['name' => 'Alex']);
    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'status' => PetStatus::available,
        'name' => 'Milo',
    ]);

    $this->actingAs($liker);
    $like = $pet->makeLike();

    Notification::assertSentTo($owner, ModelLikedNotification::class, function (ModelLikedNotification $notification) use ($like, $liker, $pet) {
        $payload = $notification->toArray($liker);

        return $payload['like_id'] === $like->id
            && $payload['liker_id'] === $liker->id
            && $payload['liker_name'] === 'Alex'
            && $payload['likeable_type'] === Pet::class
            && $payload['likeable_id'] === $pet->id
            && $payload['likeable_name'] === 'Milo'
            && $payload['message_key'] === 'notifications.liked_pet'
            && $payload['message'] === __('notifications.liked_pet', ['name' => 'Alex', 'pet' => 'Milo'])
            && $payload['url'] === route('pets.show', $pet)
            && $payload['type'] === 'like';
    });
});
