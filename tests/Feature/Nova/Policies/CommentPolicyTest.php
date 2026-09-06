<?php

use App\Models\Admin;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Report;

/**
 * A comment is a member's words, published under their name, so an admin may
 * read it and remove it but never author or rewrite it.
 *
 * `delete` is false for a different reason from `create` and `update`: not
 * because removal is wrong — removal is the whole point of moderation — but
 * because `comments.parent_id` cascades. Nova's built-in delete would take the
 * subtree at the database level without firing an Eloquent event and leave the
 * likes and reports on every descendant pointing at comments that no longer
 * exist. App\Nova\Actions\DeleteCommentThread is the only route, and
 * `runDestructiveAction` is what lets it past this same refusal.
 */
test('removes nothing when the built-in delete is aimed at a comment', function () {
    $admin = Admin::factory()->create();
    $comment = Comment::factory()->forPet()->create();
    $reply = Comment::factory()->reply($comment)->create();
    $like = Like::factory()->forComment($reply)->create();
    $report = Report::factory()->forReportable($reply)->create();

    $this->actingAs($admin, 'admin')
        ->deleteJson('/nova-api/comments', ['resources' => [$comment->getKey()]])
        ->assertOk();

    $this->assertModelExists($comment);
    $this->assertModelExists($reply);
    $this->assertModelExists($like);
    $this->assertModelExists($report);
});

test('reports a comment as not deletable in the index payload', function () {
    $admin = Admin::factory()->create();
    Comment::factory()->forPet()->create();

    $this->actingAs($admin, 'admin')
        ->getJson('/nova-api/comments')
        ->assertOk()
        ->assertJsonPath('resources.0.authorizedToDelete', false)
        ->assertJsonPath('resources.0.authorizedToUpdate', false)
        ->assertJsonPath('resources.0.authorizedToRestore', false);
});

test('returns 403 to a comment edited through Nova', function () {
    $admin = Admin::factory()->create();
    $comment = Comment::factory()->forPet()->create(['content' => 'What they said']);

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/comments/{$comment->getKey()}", ['content' => 'What a moderator says they said'])
        ->assertForbidden();

    expect($comment->fresh()->content)->toBe('What they said');
});

test('returns 403 to a comment written through Nova', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->getJson('/nova-api/comments/creation-fields')
        ->assertForbidden();
});
