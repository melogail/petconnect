<?php

use App\Models\Admin;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Report;

/**
 * The adapter onto App\Actions\Comments\DeleteComment, which owns the deletion
 * itself and is covered by tests/Feature/Actions/Comments/DeleteCommentTest.php.
 * What is proved here is the wiring and the invariant that makes the detour
 * worth taking: `comments.parent_id` cascades, so a bare delete would drop the
 * subtree at the database level with no Eloquent event and leave the likes and
 * reports on every descendant behind as moderation-queue rows whose target no
 * longer exists.
 */
test('deletes a comment, its replies and everything filed against them', function () {
    $admin = Admin::factory()->create();
    $root = Comment::factory()->forPet()->create();
    $reply = Comment::factory()->reply($root)->create();
    $sibling = Comment::factory()->forPet()->create();

    $likeOnReply = Like::factory()->forComment($reply)->create();
    $reportOnReply = Report::factory()->pending()->forReportable($reply)->create();
    $likeOnSibling = Like::factory()->forComment($sibling)->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/comments/action?action=delete-comment-with-replies', [
            'resources' => [$root->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', '1 comment thread(s) deleted, along with their replies, likes and reports.');

    $this->assertModelMissing($root);
    $this->assertModelMissing($reply);
    $this->assertModelMissing($likeOnReply);
    $this->assertModelMissing($reportOnReply);

    $this->assertModelExists($sibling);
    $this->assertModelExists($likeOnSibling);
});

test('deletes every selected thread when several are run at once', function () {
    $admin = Admin::factory()->create();
    $threads = Comment::factory()->count(3)->forPet()->create();
    $bystander = Comment::factory()->forPet()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/comments/action?action=delete-comment-with-replies', [
            'resources' => $threads->modelKeys(),
        ])
        ->assertOk()
        ->assertJsonPath('message', '3 comment thread(s) deleted, along with their replies, likes and reports.');

    expect(Comment::query()->pluck('id')->all())->toBe([$bystander->getKey()]);
});
