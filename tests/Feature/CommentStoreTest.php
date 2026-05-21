<?php

use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;

it('requires authentication to store a comment', function () {
    $pet = Pet::factory()->create();

    $this->post(route('comments.store', ['commentable_type' => 'pet', 'commentable_id' => $pet->id]), [
        'content' => 'Hello!',
    ])->assertRedirect(route('login'));
});

it('stores a top-level comment for a pet', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $pet = Pet::factory()->create();

    $this->actingAs($user)
        ->from(route('pets.show', $pet))
        ->post(route('comments.store', ['commentable_type' => 'pet', 'commentable_id' => $pet->id]), [
            'content' => 'What a cute pet!',
        ])
        ->assertRedirect(route('pets.show', $pet))
        ->assertSessionHas('success');

    expect(Comment::query()->count())->toBe(1);

    $comment = Comment::query()->first();

    expect($comment->user_id)->toBe($user->id)
        ->and($comment->commentable_type)->toBe(Pet::class)
        ->and($comment->commentable_id)->toBe($pet->id)
        ->and($comment->parent_id)->toBeNull()
        ->and($comment->content)->toBe('What a cute pet!');
});

it('stores a reply when parent_id belongs to the same pet', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $pet = Pet::factory()->create();
    $parent = Comment::factory()->create([
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
    ]);

    $this->actingAs($user)
        ->post(route('comments.store', ['commentable_type' => 'pet', 'commentable_id' => $pet->id]), [
            'content' => 'I agree!',
            'parent_id' => $parent->id,
        ])
        ->assertRedirect();

    $reply = Comment::query()->where('parent_id', $parent->id)->first();

    expect($reply)->not->toBeNull()
        ->and($reply->commentable_id)->toBe($pet->id)
        ->and($reply->content)->toBe('I agree!');
});

it('rejects replies whose parent belongs to a different resource', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $petA = Pet::factory()->create();
    $petB = Pet::factory()->create();
    $parent = Comment::factory()->create([
        'commentable_type' => Pet::class,
        'commentable_id' => $petA->id,
    ]);

    $this->actingAs($user)
        ->post(route('comments.store', ['commentable_type' => 'pet', 'commentable_id' => $petB->id]), [
            'content' => 'Cross-resource reply',
            'parent_id' => $parent->id,
        ])
        ->assertSessionHasErrors('parent_id');

    expect(Comment::query()->where('parent_id', $parent->id)->count())->toBe(0);
});

it('rejects unknown commentable types', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('comments.store', ['commentable_type' => 'unicorn', 'commentable_id' => 1]), [
            'content' => 'Nope',
        ])
        ->assertNotFound();
});

it('returns 404 when the resource does not exist', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('comments.store', ['commentable_type' => 'pet', 'commentable_id' => 999999]), [
            'content' => 'Nope',
        ])
        ->assertNotFound();
});
