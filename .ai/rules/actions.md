---
paths:
  - 'app/Actions/**'
---

# Actions

## Deleting a User bypasses every polymorphic cleanup Action — the account delete flow owns it
`users` has no SoftDeletes, and `pets`, `comments`, `reviews`, `likes`, `saves`, `reports`, `messages` and `conversation_user` all hang off `user_id`/`sender_id` with `cascadeOnDelete`. A DB cascade fires no Eloquent events, so `$user->delete()` runs none of `Actions\Reviews\DeleteReview`, `Actions\Comments\DeleteComment` or medialibrary's `deleting` hook for the cascaded rows.

Measured: A reviews B, C reports that review, A's account is deleted — the review is gone and C's report survives with `reportable` resolving to null. `comments.user_id` has the same hole, and worse: `comments.parent_id` cascades the descendants too, so the likes and reports of an entire subtree are stranded. `reviews.reviewable_id` / `likes.likeable_id` are morph columns with no FK, so deleting B strands every review and like *about* B as well. Same for the media, comments, likes, saves, reviews and reports of the deleted user's cascaded `pets`, and for their own `notifications` rows.

Rule: never delete a `User` with a bare `$user->delete()`. Go through `Actions\Profiles\DeleteUserAccount`, which collects the affected ids before the cascade can run and clears the polymorphic rows explicitly, all in one transaction. A new polymorphic child of `User` or of anything that cascades from `User` is a new step in that flow. `DeleteReview` and `DeleteComment` cover the single-row path only — do not read them as covering account deletion.
