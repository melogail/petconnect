# Reviews

## Purpose
Rate and comment on another user's profile after a transaction, building seller/adopter trust.

## Data
`reviews` (user_id, rate, comment, reviewable_type/id). Only `Reviewable::User`. Reviews are reportable.

## Backend
- Actions (`app/Actions/Reviews/`): CreateReview, UpdateReview, DeleteReview, ListReviews.
- Pipelines: SubmitReview (ResolveReviewable → EnsureNotSelfReview → EnsureNotAlreadyReviewed → SanitizeReviewComment → PersistReview → NotifyReviewee), ReviseReview (SanitizeReviewComment → PersistReviewEdit).
- `ReviewPolicy`, `ReviewValidationRules`, resources in `app/Http/Resources/Review/`.
- Notification `ModelReviewedNotification` (database).

## Routes
Web and API: `GET reviews/{type}/{id}`, `POST reviews/{type}/{id}`, `PUT reviews/{review}`, `DELETE reviews/{review}`; API also `GET profiles/{user}/reviews`.

## Frontend
Reviews section on `pages/profile/Show.vue` with rating input.

## Guards
Throttles `reviews` (10/min), `content-edits` (30/min). One review per reviewer per profile; no self-review. Bounds from `bounds.reviews`.

## Admin
Nova `Review` resource, `DeleteReview` action.

## Tests
`tests/Feature/Actions/Reviews/`, `tests/Feature/Http/Resources/Review/`.

## Open work
- Reviews are not tied to a completed conversation or listing, so anyone can review anyone. Consider gating on an existing conversation to limit abuse.
