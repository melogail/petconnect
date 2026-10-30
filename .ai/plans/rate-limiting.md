# Rate limiting

## Purpose
Every write endpoint carries a named throttle defined once in `app/Providers/RateLimitServiceProvider.php` and attached in the route files. Keys are per user id, falling back to IP for guests.

## Limits

| Name | Limit | Protects |
| --- | --- | --- |
| registrations | 5/min per caller | POST register |
| login | Fortify default | POST login |
| social-logins | 10/min, 60/h | social redirect/callback and API exchange |
| password-reset-links | 3/min, 15/h | forgot password |
| password-resets | 5/min, 20/h | reset password |
| password-confirmations | 5/min, 20/h | confirm password |
| password-changes | 6/min | settings password update |
| verification-notifications | 6/min | resend verification email |
| profile-updates | 10/min | profile edit |
| account-deletions | 10/min, 20/h | account delete |
| pet-listings | 5/min, 30/h | create pet |
| pet-listing-edits | 10/min, 60/h | update/delete/status pet |
| pet-likes, comment-likes, profile-likes | 30/min | like toggles |
| comments | 10/min | create comment |
| reviews | 10/min | create review |
| reports | 5/min | file report |
| conversations | 5/min | start conversation |
| messages | 30/min | send message |
| content-edits | 30/min | edit/delete comments, messages, reviews |
| inbox-actions | 60/min | notifications and conversation read/list actions |
| locale-switches | 60/min | POST locale |
| contact-messages | 5/min | contact form |
| ticket-messages | 5/min | open/reply ticket |

## Tests
`tests/Feature/Providers/`, `tests/Feature/Http/Middleware/`.

## Open work
- The public pet detail GET (which increments `views`) and the home feed are unthrottled.
