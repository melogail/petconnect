<?php

use App\Actions\Comments\DeleteComment;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use App\Models\Report;
use App\Pipelines\Comments\DeleteCommentThread\DeleteCommentRoot;

/**
 * A thread three levels deep, every comment in it carrying a like and a report.
 *
 * Publishing caps a thread at two levels, but nothing stops Nova, a seeder or
 * an import from writing deeper, and the delete flow collects the subtree with
 * a recursive CTE that follows parent_id to whatever depth exists — so the
 * fixture goes one level past the cap.
 *
 * `likes` and `reports` reach a comment through a morph column, which carries
 * no foreign key, so the database cascade on `comments.parent_id` cannot take
 * them with it. Removing them is the flow's whole job.
 *
 * @return array{root: Comment, reply: Comment, grandchild: Comment, pet: Pet}
 */
function reactedCommentThread(): array
{
    $pet = Pet::factory()->create();
    $root = Comment::factory()->for($pet, 'commentable')->create();
    $reply = Comment::factory()->reply($root)->create();
    $grandchild = Comment::factory()->reply($reply)->create();

    foreach ([$root, $reply, $grandchild] as $comment) {
        Like::factory()->forComment($comment)->create();
        Report::factory()->forReportable($comment)->create();
    }

    return ['root' => $root, 'reply' => $reply, 'grandchild' => $grandchild, 'pet' => $pet];
}

test('removes the whole subtree together with the likes and reports of every comment in it', function () {
    ['root' => $root, 'reply' => $reply, 'grandchild' => $grandchild] = reactedCommentThread();

    $deleted = app(DeleteComment::class)->handle($root);

    expect($deleted)->toBeTrue();

    foreach ([$root, $reply, $grandchild] as $comment) {
        $this->assertModelMissing($comment);
        $this->assertDatabaseMissing('likes', [
            'likeable_type' => 'comment',
            'likeable_id' => $comment->getKey(),
        ]);
        $this->assertDatabaseMissing('reports', [
            'reportable_type' => 'comment',
            'reportable_id' => $comment->getKey(),
        ]);
    }
});

test('leaves the likes and reports of a comment outside the subtree alone', function () {
    ['root' => $root, 'pet' => $pet] = reactedCommentThread();
    $bystander = Comment::factory()->for($pet, 'commentable')->create();
    $bystanderLike = Like::factory()->forComment($bystander)->create();
    $bystanderReport = Report::factory()->forReportable($bystander)->create();

    app(DeleteComment::class)->handle($root);

    $this->assertModelExists($bystander);
    $this->assertModelExists($bystanderLike);
    $this->assertModelExists($bystanderReport);
});

test('leaves a like on another model that happens to share an id with a deleted comment alone', function () {
    ['root' => $root, 'grandchild' => $grandchild] = reactedCommentThread();
    $collidingPet = Pet::factory()->create(['id' => $grandchild->getKey()]);
    $petLike = Like::factory()->forPet($collidingPet)->create();

    app(DeleteComment::class)->handle($root);

    $this->assertModelMissing($grandchild);
    $this->assertModelExists($petLike);
});

test('deletes nothing at all when a later step fails', function () {
    ['root' => $root, 'reply' => $reply, 'grandchild' => $grandchild] = reactedCommentThread();
    $this->mock(DeleteCommentRoot::class)
        ->shouldReceive('handle')
        ->andThrow(new RuntimeException('The root could not be removed.'));

    expect(fn (): bool => app(DeleteComment::class)->handle($root))
        ->toThrow(RuntimeException::class, 'The root could not be removed.');

    foreach ([$root, $reply, $grandchild] as $comment) {
        $this->assertModelExists($comment);
        $this->assertDatabaseHas('likes', [
            'likeable_type' => 'comment',
            'likeable_id' => $comment->getKey(),
        ]);
        $this->assertDatabaseHas('reports', [
            'reportable_type' => 'comment',
            'reportable_id' => $comment->getKey(),
        ]);
    }
});

test('deleting a reply leaves its parent and the parent reactions in place', function () {
    ['root' => $root, 'reply' => $reply, 'grandchild' => $grandchild] = reactedCommentThread();

    app(DeleteComment::class)->handle($reply);

    $this->assertModelExists($root);
    $this->assertDatabaseHas('likes', [
        'likeable_type' => 'comment',
        'likeable_id' => $root->getKey(),
    ]);
    $this->assertDatabaseHas('reports', [
        'reportable_type' => 'comment',
        'reportable_id' => $root->getKey(),
    ]);
    $this->assertModelMissing($reply);
    $this->assertModelMissing($grandchild);
});
