# Comments

## Purpose
Threaded discussion under a pet listing. Replies nest under a parent comment; comments can be liked and reported.

## Data
`comments` (user_id, content, parent_id, commentable_type/id). Only `Commentable::Pet` today. Morph children: likes, reports.

## Backend
- Actions (`app/Actions/Comments/`): CreateComment, UpdateComment, DeleteComment, ListCommentThread, ListCommentReplies, ListCommentSubtreeIds.
- Pipelines (`app/Pipelines/Comments/`): PublishComment (ResolveCommentable → RequireCommentThread → ValidateParentBelongsToCommentable → CleanContent → PersistComment → NotifyCommentable), ReviseComment (CleanContent → PersistContent), DeleteCommentThread (CollectCommentSubtree → DeleteSubtreeLikes → DeleteSubtreeReports → DeleteCommentRoot).
- Content sanitised by `app/Actions/Content/SanitizeContent.php`.
- Requests under `app/Http/Requests/Comment/`, rules in `CommentValidationRules`; resources in `app/Http/Resources/Comment/`; `CommentPolicy` (author edits/deletes; listing owner may delete).
- Notification: `ModelCommentedNotification` (database + broadcast) to the listing owner and thread participants via `commentNotificationRecipients()`.

## Routes
Web and API: `GET comments/{type}/{id}`, `POST comments/{type}/{id}`, `PUT comments/{comment}`, `DELETE comments/{comment}`, `GET comments/{comment}/replies`, `POST comments/{comment}/like`.

## Frontend
Comment thread components rendered inside `pages/pets/Show.vue`.

## Guards
Throttles `comments` (10/min), `content-edits` (30/min), `comment-likes` (30/min). Max length from `bounds.comments`.

## Admin
Nova `Comment` resource, `DeleteCommentThread` action.

## Tests
`tests/Feature/Actions/Comments/`, `tests/Feature/Http/Resources/Comment/`, `tests/Feature/Policies/`.

## Open work
None known.
