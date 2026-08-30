<?php

use App\Models\Comment;
use Illuminate\Support\Str;

test('deleting a comment deletes its replies', function () {
    $parent = Comment::factory()->create();
    $reply = Comment::factory()->reply($parent)->create();

    $parent->delete();

    $this->assertDatabaseMissing('comments', ['id' => $reply->getKey()]);
});

test('deleting a comment deletes replies nested below its replies', function () {
    $parent = Comment::factory()->create();
    $reply = Comment::factory()->reply($parent)->create();
    $nestedReply = Comment::factory()->reply($reply)->create();

    $parent->delete();

    $this->assertDatabaseEmpty('comments');
    $this->assertModelMissing($nestedReply);
});

test('deleting a reply leaves its parent alone', function () {
    $parent = Comment::factory()->create();
    $reply = Comment::factory()->reply($parent)->create();

    $reply->delete();

    $this->assertModelExists($parent);
});

test('stores a comment longer than 255 characters intact', function () {
    $content = Str::repeat('a', 300);

    $comment = Comment::factory()->create(['content' => $content]);

    expect($comment->fresh()->content)->toBe($content);
});
