<?php

use App\Enums\PetStatus;
use App\Models\Pet;
use Inertia\Testing\AssertableInertia as Assert;

it('accepts valid nearby coordinates on the home feed', function () {
    Pet::factory()->create([
        'status' => PetStatus::available,
        'latitude' => 30.0444,
        'longitude' => 31.2357,
    ]);

    $this->get(route('home', [
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'radius' => 20,
    ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('nearby', true)
            ->where('radius', 20)
            ->has('pets.data', 1)
            ->where('pets.data.0.distance', fn ($distance) => $distance <= 1)
        );
});

it('rejects invalid latitude', function () {
    $this->getJson(route('home', [
        'latitude' => 100,
        'longitude' => 31.2357,
        'radius' => 20,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude']);
});

it('rejects invalid longitude', function () {
    $this->getJson(route('home', [
        'latitude' => 30.0444,
        'longitude' => 200,
        'radius' => 20,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['longitude']);
});

it('rejects invalid radius', function (mixed $radius) {
    $this->getJson(route('home', [
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'radius' => $radius,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['radius']);
})->with([
    'too small' => [0],
    'too large' => [250],
    'frontend oversize' => [500],
]);

it('accepts a radius within a raised configured max', function () {
    config([
        'petconnect.nearby.default_radius_km' => 500,
        'petconnect.nearby.max_radius_km' => 500,
    ]);

    Pet::factory()->create([
        'status' => PetStatus::available,
        'latitude' => 30.0444,
        'longitude' => 31.2357,
    ]);

    $this->get(route('home', [
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'radius' => 500,
    ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('nearby', true)
            ->where('radius', 500)
            ->where('defaultRadius', 500)
            ->where('maxRadius', 500)
        );
});

it('shares configured nearby radius defaults on the home page', function () {
    config([
        'petconnect.nearby.default_radius_km' => 35,
        'petconnect.nearby.max_radius_km' => 150,
    ]);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('defaultRadius', 35)
            ->where('maxRadius', 150)
        );
});

it('redirects home visits with an oversized radius back with errors', function () {
    $this->from(route('home'))
        ->get(route('home', [
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'radius' => 500,
        ]))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors(['radius']);
});

it('requires both coordinates when only one is provided', function () {
    $this->getJson(route('home', [
        'latitude' => 30.0444,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['longitude']);
});

it('returns only pets inside the radius ordered by distance', function () {
    $originLat = 30.0444;
    $originLng = 31.2357;

    $nearest = Pet::factory()->create([
        'name' => 'Nearest',
        'status' => PetStatus::available,
        'latitude' => 30.05,
        'longitude' => 31.24,
    ]);

    $mid = Pet::factory()->create([
        'name' => 'Mid',
        'status' => PetStatus::available,
        'latitude' => 30.1,
        'longitude' => 31.3,
    ]);

    Pet::factory()->create([
        'name' => 'Far',
        'status' => PetStatus::available,
        'latitude' => 31.5,
        'longitude' => 32.5,
    ]);

    $response = $this->getJson(route('home', [
        'latitude' => $originLat,
        'longitude' => $originLng,
        'radius' => 20,
    ]));

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');

    $ids = collect($response->json('data'))->pluck('id')->all();
    $distances = collect($response->json('data'))->pluck('distance')->all();

    expect($ids)->toBe([$nearest->id, $mid->id])
        ->and($distances[0])->toBeLessThan($distances[1])
        ->and($distances[0])->toBeGreaterThan(0)
        ->and($distances[1])->toBeLessThan(20);
});

it('excludes pets outside the radius', function () {
    Pet::factory()->create([
        'status' => PetStatus::available,
        'latitude' => 31.5,
        'longitude' => 32.5,
    ]);

    $this->getJson(route('home', [
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'radius' => 5,
    ]))
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('calculates distance approximately correctly', function () {
    // ~1.11 km north of origin (approx 0.01 degrees latitude)
    Pet::factory()->create([
        'status' => PetStatus::available,
        'latitude' => 30.0544,
        'longitude' => 31.2357,
    ]);

    $distance = $this->getJson(route('home', [
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'radius' => 20,
    ]))->json('data.0.distance');

    expect($distance)->toBeGreaterThan(0.9)
        ->and($distance)->toBeLessThan(1.3);
});

it('paginates nearby results', function () {
    $category = \App\Models\Category::factory()->create();
    $breed = \App\Models\Breed::factory()->create(['category_id' => $category->id]);

    Pet::factory()->count(13)->create([
        'status' => PetStatus::available,
        'category_id' => $category->id,
        'breed_id' => $breed->id,
        'latitude' => 30.0444,
        'longitude' => 31.2357,
    ]);

    $this->getJson(route('home', [
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'radius' => 20,
        'page' => 1,
    ]))
        ->assertSuccessful()
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 13);

    $this->getJson(route('home', [
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'radius' => 20,
        'page' => 2,
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('orders pets by newest first when location is not provided', function () {
    $newer = Pet::factory()->create([
        'name' => 'Newer',
        'status' => PetStatus::available,
        'created_at' => now(),
    ]);

    $older = Pet::factory()->create([
        'name' => 'Older',
        'status' => PetStatus::available,
        'created_at' => now()->subDay(),
    ]);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('nearby', false)
            ->where('pets.data.0.id', $newer->id)
            ->where('pets.data.1.id', $older->id)
            ->missing('pets.data.0.distance')
        );

    $this->getJson(route('home'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

it('only returns available pets for nearby search', function () {
    Pet::factory()->create([
        'status' => PetStatus::available,
        'latitude' => 30.0444,
        'longitude' => 31.2357,
    ]);

    Pet::factory()->create([
        'status' => PetStatus::unavailable,
        'latitude' => 30.0444,
        'longitude' => 31.2357,
    ]);

    $this->getJson(route('home', [
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'radius' => 20,
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('handles antimeridian longitude bounds without error', function () {
    Pet::factory()->create([
        'status' => PetStatus::available,
        'latitude' => 0,
        'longitude' => 179.9,
    ]);

    $this->getJson(route('home', [
        'latitude' => 0,
        'longitude' => 179.95,
        'radius' => 50,
    ]))->assertSuccessful();
});
