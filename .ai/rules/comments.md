---
paths:
  - 'app/Actions/Comments/**'
---

# Comments

## Walk a comment subtree with ListCommentSubtreeIds, never a frontier loop
Both delete flows (Pipelines\Comments\DeleteCommentThread\CollectCommentSubtree and Pipelines\Profiles\DeleteAccount\CollectAccountContent) get the subtree from Actions\Comments\ListCommentSubtreeIds: one recursive CTE, one binding per root. They used to loop one whereIn('parent_id') per level, accumulating an unbounded PHP array that the account flow fed back as whereNotIn('id', $collected) — a bound-parameter list growing on every iteration, inside the delete's open transaction.

No cycle guard is needed: comments.parent_id is a single nullable self-reference, so the rows are a tree. The CTE uses `union` (distinct), not `union all`, because the account flow's root set can already contain a descendant of another root, and distinct union also terminates on cyclic data.

Recursive CTEs are available on both drivers this project runs on, verified not assumed: MySQL 8.0.46 in dev (since 8.0.1, cte_max_recursion_depth 1000 — a loud error, not a hang) and SQLite 3.45.1 in the test suite (since 3.8.3). The polymorphic cleanup is unchanged: the subtree is still collected first and likes/reports still deleted explicitly inside the one transaction the Action opens.
