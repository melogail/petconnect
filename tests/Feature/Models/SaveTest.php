<?php

use App\Models\Pet;
use App\Models\Save;
use App\Models\User;

test('stores the pet morph alias rather than a class name', function () {
    $pet = Pet::factory()->create();

    $save = Save::factory()->forPet($pet)->create();

    $this->assertDatabaseHas('saves', [
        'id' => $save->getKey(),
        'saveable_type' => 'pet',
        'saveable_id' => $pet->getKey(),
    ]);
});

test('resolves the saved pet from the stored alias', function () {
    $pet = Pet::factory()->create();
    $save = Save::factory()->forPet($pet)->create();

    $saveable = Save::query()->findOrFail($save->getKey())->saveable;

    expect($saveable)->toBeInstanceOf(Pet::class)
        ->and($saveable->is($pet))->toBeTrue();
});

test('bookmarking a pet through the model stores the alias too', function () {
    $pet = Pet::factory()->create();
    $user = User::factory()->create();

    $pet->addSave($user);

    $this->assertDatabaseHas('saves', [
        'user_id' => $user->getKey(),
        'saveable_type' => 'pet',
        'saveable_id' => $pet->getKey(),
    ]);
});
