<?php

use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('reading a thread is allowed for a guest', function () {
    expect(Gate::forUser(null)->allows('viewAny', Comment::class))->toBeTrue();
});

test('reading one comment is allowed for a guest', function () {
    $comment = Comment::factory()->create();

    expect(Gate::forUser(null)->allows('view', $comment))->toBeTrue();
});

test('publishing is allowed for a verified user and refused for an unverified one', function () {
    $verified = User::factory()->create();
    $unverified = User::factory()->unverified()->create();

    expect($verified->can('create', Comment::class))->toBeTrue()
        ->and($unverified->can('create', Comment::class))->toBeFalse();
});

test('the author may manage their own comment', function (string $ability) {
    $author = User::factory()->create();
    $comment = Comment::factory()->for($author)->create();

    expect($author->can($ability, $comment))->toBeTrue();
})->with(['update', 'delete']);

test('a user who did not write the comment may not manage it', function (string $ability) {
    $comment = Comment::factory()->create();
    $stranger = User::factory()->create();

    expect($stranger->can($ability, $comment))->toBeFalse();
})->with(['update', 'delete']);

test('the owner of the commented listing may not manage a comment they did not write', function (string $ability) {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $comment = Comment::factory()->for($pet, 'commentable')->create();

    expect($owner->can($ability, $comment))->toBeFalse();
})->with(['update', 'delete']);

test('an unverified author may not manage their own comment', function (string $ability) {
    $author = User::factory()->unverified()->create();
    $comment = Comment::factory()->for($author)->create();

    expect($author->can($ability, $comment))->toBeFalse();
})->with(['update', 'delete']);

test('liking is allowed for any verified user and refused for an unverified one', function () {
    $comment = Comment::factory()->create();
    $verified = User::factory()->create();
    $unverified = User::factory()->unverified()->create();

    expect($verified->can('like', $comment))->toBeTrue()
        ->and($unverified->can('like', $comment))->toBeFalse();
});
