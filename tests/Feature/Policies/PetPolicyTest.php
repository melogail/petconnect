<?php

use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('browsing listings is allowed for a guest', function () {
    expect(Gate::forUser(null)->allows('viewAny', Pet::class))->toBeTrue();
});

test('viewing a listing is allowed for a guest', function () {
    $pet = Pet::factory()->create();

    expect(Gate::forUser(null)->allows('view', $pet))->toBeTrue();
});

test('publishing is allowed for a verified user and refused for an unverified one', function () {
    $verified = User::factory()->create();
    $unverified = User::factory()->unverified()->create();

    expect($verified->can('create', Pet::class))->toBeTrue()
        ->and($unverified->can('create', Pet::class))->toBeFalse();
});

test('the owner may manage their own listing', function (string $ability) {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    expect($owner->can($ability, $pet))->toBeTrue();
})->with(['update', 'delete', 'restore', 'forceDelete']);

test('a user who does not own the listing may not manage it', function (string $ability) {
    $pet = Pet::factory()->create();
    $stranger = User::factory()->create();

    expect($stranger->can($ability, $pet))->toBeFalse();
})->with(['update', 'delete', 'restore', 'forceDelete']);

test('an unverified owner may not manage their own listing', function (string $ability) {
    $owner = User::factory()->unverified()->create();
    $pet = Pet::factory()->for($owner)->create();

    expect($owner->can($ability, $pet))->toBeFalse();
})->with(['update', 'delete', 'restore', 'forceDelete']);

test('liking is allowed for any verified user and refused for an unverified one', function () {
    $pet = Pet::factory()->create();
    $verified = User::factory()->create();
    $unverified = User::factory()->unverified()->create();

    expect($verified->can('like', $pet))->toBeTrue()
        ->and($unverified->can('like', $pet))->toBeFalse();
});
