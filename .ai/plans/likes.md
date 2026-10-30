# Likes

## Purpose
One-tap appreciation on pets, comments and user profiles. Toggle semantics: a second call removes the like.

## Data
`likes` (user_id, likeable_type, likeable_id). Likeables: Pet, Comment, User (via `HasLikes` + `Likeable` contract).

## Backend
- `app/Actions/Likes/ToggleLike.php`, `CountLikes.php`.
- `LikeObserver` sends `ModelLikedNotification` (database + broadcast) to `likeNotificationRecipients()` of the target; self-likes do not notify.
- `ResolvesMorphTarget` maps the route's type segment to the model.

## Routes
Web and API: `POST pets/{pet}/like`, `POST comments/{comment}/like`, `POST profile/{user}/like` (API: `profiles/{user}/like`). Auth required.

## Frontend
Like buttons on pet cards, pet detail, comment items and the profile header; optimistic toggle.

## Guards
Throttles `pet-likes`, `comment-likes`, `profile-likes` (30/min each).

## Tests
`tests/Feature/Actions/Likes/`, `tests/Feature/Observers/`.

## Open work
None known.
