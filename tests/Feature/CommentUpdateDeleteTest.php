<?php

use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;

it('lets a verified owner update their comment', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $pet = Pet::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
        'content' => 'Original content',
    ]);

    $this->actingAs($user)
        ->from(route('pets.show', $pet))
        ->put(route('comments.update', $comment), [
            'content' => 'Updated content',
        ])
        ->assertRedirect(route('pets.show', $pet))
        ->assertSessionHas('success');

    expect($comment->fresh()->content)->toBe('Updated content');
});

it('forbids non-owners from updating a comment', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $pet = Pet::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $owner->id,
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
        'content' => 'Owned content',
    ]);

    $this->actingAs($other)
        ->put(route('comments.update', $comment), [
            'content' => 'Hijacked content',
        ])
        ->assertForbidden();

    expect($comment->fresh()->content)->toBe('Owned content');
});

it('forbids unverified users from updating their own comment', function () {
    $user = User::factory()->unverified()->create();
    $pet = Pet::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
        'content' => 'Unverified content',
    ]);

    $this->actingAs($user)
        ->put(route('comments.update', $comment), [
            'content' => 'Trying to update',
        ])
        ->assertRedirect(route('verification.notice'));

    expect($comment->fresh()->content)->toBe('Unverified content');
});

it('lets a verified owner delete their comment and replies', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $pet = Pet::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
    ]);
    $reply = Comment::factory()->create([
        'user_id' => $user->id,
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
        'parent_id' => $comment->id,
    ]);

    $this->actingAs($user)
        ->from(route('pets.show', $pet))
        ->delete(route('comments.delete', $comment))
        ->assertRedirect(route('pets.show', $pet))
        ->assertSessionHas('success');

    expect(Comment::query()->whereKey($comment->id)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey($reply->id)->exists())->toBeFalse();
});

it('forbids non-owners from deleting a comment', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $pet = Pet::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $owner->id,
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
    ]);

    $this->actingAs($other)
        ->delete(route('comments.delete', $comment))
        ->assertForbidden();

    expect(Comment::query()->whereKey($comment->id)->exists())->toBeTrue();
});

it('requires authentication to update or delete a comment', function () {
    $pet = Pet::factory()->create();
    $comment = Comment::factory()->create([
        'commentable_type' => Pet::class,
        'commentable_id' => $pet->id,
    ]);

    $this->put(route('comments.update', $comment), ['content' => 'X'])
        ->assertRedirect(route('login'));

    $this->delete(route('comments.delete', $comment))
        ->assertRedirect(route('login'));
});
