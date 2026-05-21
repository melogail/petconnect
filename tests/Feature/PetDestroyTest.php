<?php

use App\Models\Pet;
use App\Models\User;
use Database\Seeders\CategorySeeder;

test('pet owner can delete their listing and receives success flash', function () {
    $this->seed(CategorySeeder::class);

    $owner = User::factory()->create();
    $other = User::factory()->create();

    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'category_id' => \App\Models\Category::first()->id,
    ]);

    $response = $this->actingAs($owner)->delete(route('pets.destroy', $pet));

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('success');

    expect(Pet::query()->whereKey($pet->id)->exists())->toBeFalse();
});

test('non-owner cannot delete someone elses pet', function () {
    $this->seed(CategorySeeder::class);

    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'category_id' => \App\Models\Category::first()->id,
    ]);

    $response = $this->actingAs($intruder)->delete(route('pets.destroy', $pet));

    $response->assertForbidden();

    expect(Pet::query()->whereKey($pet->id)->exists())->toBeTrue();
});

test('guest cannot delete a pet', function () {
    $this->seed(CategorySeeder::class);

    $owner = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'category_id' => \App\Models\Category::first()->id,
    ]);

    $response = $this->delete(route('pets.destroy', $pet));

    $response->assertRedirect();

    expect(Pet::query()->whereKey($pet->id)->exists())->toBeTrue();
});
