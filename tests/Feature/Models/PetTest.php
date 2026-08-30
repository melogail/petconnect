<?php

use App\Models\Pet;
use Illuminate\Database\Eloquent\MassAssignmentException;

test('nearby keeps a listing at the exact search point', function () {
    Pet::factory()->at(0.0, 0.0)->create();

    $found = Pet::query()->nearby(0.0, 0.0, 10.0)->get();

    expect($found)->toHaveCount(1);
});

test('nearby excludes a listing beyond the radius', function () {
    Pet::factory()->at(0.0, 0.0)->create();
    Pet::factory()->at(1.0, 0.0)->create();

    $found = Pet::query()->nearby(0.0, 0.0, 50.0)->pluck('latitude');

    expect($found->all())->toEqualCanonicalizing(['0.00000000']);
});

test('nearby excludes a listing with no coordinates', function () {
    Pet::factory()->at(0.0, 0.0)->create();
    Pet::factory()->create(['latitude' => null, 'longitude' => null]);

    $found = Pet::query()->nearby(0.0, 0.0, 10.0)->get();

    expect($found)->toHaveCount(1);
});

test('nearby matches listings on both sides of the antimeridian', function () {
    Pet::factory()->at(0.0, 179.95)->create();
    Pet::factory()->at(0.0, -179.95)->create();
    Pet::factory()->at(0.0, 178.0)->create();

    $found = Pet::query()->nearby(0.0, 179.9, 50.0)->pluck('longitude');

    expect($found->all())->toEqualCanonicalizing(['179.95000000', '-179.95000000']);
});

test('nearby adds no select or order so the query can still be counted', function () {
    Pet::factory()->at(0.0, 0.0)->create();
    Pet::factory()->at(0.01, 0.01)->create();
    Pet::factory()->at(1.0, 0.0)->create();

    $count = Pet::query()->nearby(0.0, 0.0, 10.0)->count();

    expect($count)->toBe(2);
});

test('withDistance reports zero kilometres for a listing at the search point', function () {
    Pet::factory()->at(0.0, 0.0)->create();

    $pet = Pet::query()->withDistance(0.0, 0.0)->first();

    expect((float) $pet->distance)->toBe(0.0);
});

test('withDistance reports one degree of latitude as its great circle distance', function () {
    Pet::factory()->at(1.0, 0.0)->create();

    $pet = Pet::query()->withDistance(0.0, 0.0)->first();

    // One degree of a great circle on a sphere of the earth's mean radius: pi * 6371 / 180.
    expect(round((float) $pet->distance, 3))->toBe(111.195);
});

test('withDistance keeps every column of the listing', function () {
    $created = Pet::factory()->at(0.0, 0.0)->create(['name' => 'Bella', 'city' => 'Cairo']);

    $pet = Pet::query()->withDistance(0.0, 0.0)->first();

    expect($pet->name)->toBe('Bella')
        ->and($pet->city)->toBe('Cairo')
        ->and($pet->description)->toBe($created->description)
        ->and($pet->getKey())->toBe($created->getKey());
});

test('withDistance orders listings from nearest to furthest', function () {
    $furthest = Pet::factory()->at(1.0, 0.0)->create();
    $nearest = Pet::factory()->at(0.0, 0.0)->create();
    $middle = Pet::factory()->at(0.5, 0.0)->create();

    $ordered = Pet::query()->withDistance(0.0, 0.0)->pluck('id');

    expect($ordered->all())->toBe([$nearest->getKey(), $middle->getKey(), $furthest->getKey()]);
});

test('withDistance can be paginated', function () {
    Pet::factory()->at(0.0, 0.0)->create();
    Pet::factory()->at(0.1, 0.0)->create();
    Pet::factory()->at(0.2, 0.0)->create();

    $page = Pet::query()->withDistance(0.0, 0.0)->paginate(2);

    expect($page->total())->toBe(3)
        ->and($page->count())->toBe(2);
});

test('nearby can be paginated', function () {
    Pet::factory()->at(0.0, 0.0)->create();
    Pet::factory()->at(0.1, 0.0)->create();
    Pet::factory()->at(1.0, 0.0)->create();

    $page = Pet::query()->nearby(0.0, 0.0, 50.0)->paginate(1);

    expect($page->total())->toBe(2)
        ->and($page->count())->toBe(1);
});

test('nearby and withDistance paginate together, nearest first', function () {
    $nearest = Pet::factory()->at(0.0, 0.0)->create();
    $middle = Pet::factory()->at(0.1, 0.0)->create();
    Pet::factory()->at(1.0, 0.0)->create();

    $page = Pet::query()
        ->nearby(0.0, 0.0, 50.0)
        ->withDistance(0.0, 0.0)
        ->paginate(2);

    expect($page->total())->toBe(2)
        ->and($page->pluck('id')->all())->toBe([$nearest->getKey(), $middle->getKey()]);
});

test('rejects a mass assigned view count', function () {
    $pet = Pet::factory()->create(['views' => 7]);

    expect(fn () => $pet->fill(['views' => 9999]))
        ->toThrow(MassAssignmentException::class);

    expect($pet->fresh()->views)->toBe(7);
});
