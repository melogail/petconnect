<?php

use App\Models\User;

test('assigns a media directory name when the account is created', function () {
    $user = User::factory()->create();

    expect($user->media_directory_name)->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('users', [
        'id' => $user->getKey(),
        'media_directory_name' => $user->media_directory_name,
    ]);
});

test('gives every account its own media directory name', function () {
    $users = User::factory()->count(25)->create();

    expect($users->pluck('media_directory_name')->unique())->toHaveCount(25);
});

test('keeps a media directory name that was supplied explicitly', function () {
    $user = User::factory()->create(['media_directory_name' => '1234567890123456']);

    expect($user->media_directory_name)->toBe('1234567890123456');
});
