<?php

use App\Models\Pet;
use App\Models\Review;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('show', function () {
    test('a guest can read a profile', function () {
        $profile = User::factory()->create();
        Pet::factory()->count(2)->create(['user_id' => $profile->getKey()]);

        $this->getJson(route('api.v1.profiles.show', $profile))
            ->assertOk()
            ->assertJsonPath('id', $profile->getKey())
            ->assertJsonPath('pets_count', 2)
            ->assertJsonPath('is_self', false);
    });

    test('a deactivated profile is a 404', function () {
        $profile = User::factory()->inactive()->create();

        $this->getJson(route('api.v1.profiles.show', $profile))->assertNotFound();
    });

    test('the listings and reviews pages keep their own envelopes', function () {
        $profile = User::factory()->create();
        Pet::factory()->count(2)->create(['user_id' => $profile->getKey()]);
        Review::factory()->count(3)->create([
            'reviewable_type' => 'user',
            'reviewable_id' => $profile->getKey(),
        ]);

        $this->getJson(route('api.v1.profiles.pets', $profile))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $this->getJson(route('api.v1.profiles.reviews', $profile))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    });
});

describe('like', function () {
    test('toggles and reports the count', function () {
        $profile = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.profiles.like', $profile))
            ->assertOk()
            ->assertExactJson(['is_liked' => true, 'likes_count' => 1]);
    });

    test('requires a token', function () {
        $this->postJson(route('api.v1.profiles.like', User::factory()->create()))->assertUnauthorized();
    });
});
