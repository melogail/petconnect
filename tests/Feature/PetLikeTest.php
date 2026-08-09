<?php

use App\Enums\PetStatus;
use App\Models\Like;
use App\Models\Pet;
use App\Models\User;
use App\Notifications\ModelLikedNotification;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
});

it('allows a verified user to like a pet via the route', function () {
    $user = User::factory()->create();
    $pet = Pet::factory()->create(['status' => PetStatus::available]);

    $this->actingAs($user)
        ->from(route('home'))
        ->post(route('pets.like', $pet))
        ->assertRedirect(route('home'))
        ->assertSessionHas('success', __('flash.pet_liked'));

    $this->assertDatabaseHas('likes', [
        'user_id' => $user->id,
        'likeable_type' => Pet::class,
        'likeable_id' => $pet->id,
    ]);
});

it('allows a verified user to unlike a pet via the route', function () {
    $user = User::factory()->create();
    $pet = Pet::factory()->create(['status' => PetStatus::available]);

    $this->actingAs($user);
    $pet->makeLike();

    $this->from(route('home'))
        ->post(route('pets.like', $pet))
        ->assertRedirect(route('home'))
        ->assertSessionHas('success', __('flash.pet_unliked'));

    $this->assertDatabaseMissing('likes', [
        'user_id' => $user->id,
        'likeable_type' => Pet::class,
        'likeable_id' => $pet->id,
    ]);
});

it('redirects guests away from liking a pet', function () {
    $pet = Pet::factory()->create(['status' => PetStatus::available]);

    $this->post(route('pets.like', $pet))
        ->assertRedirect(route('login'));
});

it('redirects unverified users away from liking a pet', function () {
    $user = User::factory()->unverified()->create();
    $pet = Pet::factory()->create(['status' => PetStatus::available]);

    $this->actingAs($user)
        ->post(route('pets.like', $pet))
        ->assertRedirect(route('verification.notice'));
});

it('includes isLiked on home pet cards for the current user', function () {
    $user = User::factory()->create();
    $likedPet = Pet::factory()->create(['status' => PetStatus::available]);
    $otherPet = Pet::factory()->create(['status' => PetStatus::available]);

    $this->actingAs($user);
    $likedPet->makeLike();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->has('pets.data', 2)
            ->where('pets.data', function ($pets) use ($likedPet, $otherPet) {
                $liked = collect($pets)->firstWhere('id', $likedPet->id);
                $other = collect($pets)->firstWhere('id', $otherPet->id);

                return $liked['isLiked'] === true
                    && $liked['likesCount'] === 1
                    && $other['isLiked'] === false
                    && $other['likesCount'] === 0;
            })
        );
});

it('toggles like correctly on the pet model', function () {
    $user = User::factory()->create();
    $pet = Pet::factory()->create(['status' => PetStatus::available]);

    $this->actingAs($user);

    expect($pet->toggleLike())->toBeTrue()
        ->and($pet->isLikedBy($user))->toBeTrue()
        ->and($pet->toggleLike())->toBeFalse()
        ->and($pet->isLikedBy($user))->toBeFalse()
        ->and(Like::query()->whereMorphedTo('likeable', $pet)->count())->toBe(0);
});

it('notifies the owner when a pet is liked through the route', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $liker = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'status' => PetStatus::available,
    ]);

    $this->actingAs($liker)
        ->post(route('pets.like', $pet))
        ->assertRedirect();

    Notification::assertSentTo($owner, ModelLikedNotification::class);
    Notification::assertNotSentTo($liker, ModelLikedNotification::class);
});
