<?php

namespace App\Actions\Comments;

use App\Models\Comment;

/**
 * Every comment id at or below a set of roots, in one query.
 *
 * ## Why an Action and not a private helper on the step
 *
 * Two flows walk the same tree for the same reason —
 * Pipelines\Comments\DeleteCommentThread\CollectCommentSubtree deleting one
 * thread, and Pipelines\Profiles\DeleteAccount\CollectAccountContent deleting
 * an account's comments and the comments on its listings. A second caller is
 * exactly the condition .ai/rules/pipelines.md sets for lifting work out of a
 * step, and the walk is the part of both flows worth pinning down on its own.
 *
 * ## Why a recursive CTE and not a level-by-level loop
 *
 * Both callers used to loop: one `whereIn('parent_id', $frontier)` per level,
 * accumulating ids into a PHP array that the account flow then fed back as
 * `whereNotIn('id', $collected)` to guard against cycles. That array is
 * unbounded in memory, the `whereNotIn` grows one bound parameter per comment
 * already seen on *every* iteration, and all of it runs inside the open
 * transaction the delete needs — so a deep or wide thread holds its locks while
 * the queries get steadily longer.
 *
 * A recursive CTE is one query with one binding per root, and the cycle guard
 * goes away with it: `comments.parent_id` is a single nullable self-reference,
 * so the rows form a tree, not a graph. `union` (distinct) rather than
 * `union all` because the root set of the account flow may already contain a
 * descendant of another root — the account's own reply to its own comment — and
 * distinct union is also what makes the recursion terminate rather than spin if
 * the data were ever cyclic.
 *
 * Available on both drivers this project runs on, verified rather than assumed:
 * MySQL 8.0.46 in development (recursive CTEs since 8.0.1) and SQLite 3.45.1
 * under phpunit.xml's `:memory:` connection (since 3.8.3). MySQL caps recursion
 * at `cte_max_recursion_depth` (1000 here) and raises an error past it, which is
 * a loud failure on a thread no publishing path can create — PublishComment caps
 * threads at two levels.
 *
 * The SQL is built from the grammar's own `wrapTable()`, so the table prefix and
 * the driver's quoting come from the connection rather than from a literal, and
 * the only values interpolated are placeholders.
 */
class ListCommentSubtreeIds
{
    /**
     * @param  list<int>  $rootIds  The comments to start from; included in the result.
     * @return list<int> The roots and every descendant, in no significant order.
     */
    public function handle(array $rootIds): array
    {
        if ($rootIds === []) {
            return [];
        }

        $comment = new Comment;
        $connection = $comment->getConnection();
        $comments = $connection->getQueryGrammar()->wrapTable($comment->getTable());
        $placeholders = implode(', ', array_fill(0, count($rootIds), '?'));

        $rows = $connection->select(
            'with recursive comment_subtree (id) as ('
            ."select id from {$comments} where id in ({$placeholders})"
            .' union '
            ."select descendant.id from {$comments} as descendant"
            .' inner join comment_subtree on descendant.parent_id = comment_subtree.id'
            .') select id from comment_subtree',
            $rootIds,
        );

        return array_values(collect($rows)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }
}
