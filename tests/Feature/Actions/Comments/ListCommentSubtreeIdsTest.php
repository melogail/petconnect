<?php

use App\Actions\Comments\ListCommentSubtreeIds;
use App\Models\Comment;
use App\Models\Pet;
use Illuminate\Support\Facades\DB;

/**
 * The Action is the only hand-written SQL on an application path, and both
 * delete flows depend on it returning the roots *and* every descendant with
 * nothing extra. Reaching it only through DeleteComment and DeleteUserAccount
 * proves those flows work, not that the contract in the docblock holds — so
 * the walk is exercised directly here.
 *
 * @return array{root: Comment, reply: Comment, grandchild: Comment, greatGrandchild: Comment, bystander: Comment}
 */
function commentChainBesideAnotherTree(): array
{
    $pet = Pet::factory()->create();
    $root = Comment::factory()->for($pet, 'commentable')->create();
    $reply = Comment::factory()->reply($root)->create();
    $grandchild = Comment::factory()->reply($reply)->create();
    $greatGrandchild = Comment::factory()->reply($grandchild)->create();

    $bystander = Comment::factory()->for($pet, 'commentable')->create();
    Comment::factory()->reply($bystander)->create();

    return [
        'root' => $root,
        'reply' => $reply,
        'grandchild' => $grandchild,
        'greatGrandchild' => $greatGrandchild,
        'bystander' => $bystander,
    ];
}

/**
 * The empty root list is the case the SQL cannot express: `id in ()` is a
 * syntax error on both drivers, so the guard has to hold before the query is
 * built rather than be caught by it.
 */
test('returns nothing for an empty root list without issuing a query', function () {
    commentChainBesideAnotherTree();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect(app(ListCommentSubtreeIds::class)->handle([]))->toBe([])
        ->and($queries)->toBe(0);
});

/**
 * Four levels, because a walk that stopped at children and grandchildren would
 * still pass on the two-level thread publishing is capped at. The second tree
 * is what says the answer is the subtree rather than the table.
 */
test('returns the root and every descendant down a four level chain and nothing outside it', function () {
    ['root' => $root, 'reply' => $reply, 'grandchild' => $grandchild, 'greatGrandchild' => $greatGrandchild] = commentChainBesideAnotherTree();

    expect(app(ListCommentSubtreeIds::class)->handle([$root->getKey()]))
        ->toEqualCanonicalizing([
            $root->getKey(),
            $reply->getKey(),
            $grandchild->getKey(),
            $greatGrandchild->getKey(),
        ]);
});

test('returns a leaf root as itself alone', function () {
    ['greatGrandchild' => $greatGrandchild] = commentChainBesideAnotherTree();

    expect(app(ListCommentSubtreeIds::class)->handle([$greatGrandchild->getKey()]))
        ->toBe([$greatGrandchild->getKey()]);
});

/**
 * The account purge's root set really does contain a comment that is also a
 * descendant of another root — the account's own reply to its own comment — so
 * the two recursion branches reach the same rows. `union all` would hand the
 * delete steps the overlap twice; `union` is what this pins.
 */
test('returns each id once when a root is also a descendant of another root', function () {
    ['root' => $root, 'reply' => $reply, 'grandchild' => $grandchild, 'greatGrandchild' => $greatGrandchild] = commentChainBesideAnotherTree();

    $ids = app(ListCommentSubtreeIds::class)->handle([$root->getKey(), $grandchild->getKey()]);

    expect($ids)->toEqualCanonicalizing([
        $root->getKey(),
        $reply->getKey(),
        $grandchild->getKey(),
        $greatGrandchild->getKey(),
    ])->and($ids)->toHaveCount(count(array_unique($ids)));
});

test('returns each id once when the same root is passed twice', function () {
    ['root' => $root, 'reply' => $reply, 'grandchild' => $grandchild, 'greatGrandchild' => $greatGrandchild] = commentChainBesideAnotherTree();

    $ids = app(ListCommentSubtreeIds::class)->handle([$root->getKey(), $root->getKey()]);

    expect($ids)->toEqualCanonicalizing([
        $root->getKey(),
        $reply->getKey(),
        $grandchild->getKey(),
        $greatGrandchild->getKey(),
    ])->and($ids)->toHaveCount(count(array_unique($ids)));
});

/**
 * Several roots at once is how both callers use it: the account flow hands over
 * the account's own comments and the comments on its listings together.
 */
test('returns the union of the subtrees when several unrelated roots are given', function () {
    ['reply' => $reply, 'grandchild' => $grandchild, 'greatGrandchild' => $greatGrandchild, 'bystander' => $bystander] = commentChainBesideAnotherTree();

    $bystanderReply = Comment::query()->where('parent_id', $bystander->getKey())->sole();

    expect(app(ListCommentSubtreeIds::class)->handle([$reply->getKey(), $bystander->getKey()]))
        ->toEqualCanonicalizing([
            $reply->getKey(),
            $grandchild->getKey(),
            $greatGrandchild->getKey(),
            $bystander->getKey(),
            $bystanderReply->getKey(),
        ]);
});
