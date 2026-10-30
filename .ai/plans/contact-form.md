# Contact form

## Purpose
Lightweight "get in touch" for guests and users from the Support page, without creating a ticket thread.

## Data
`contact_messages` (name, email, subject, message).

## Backend
`app/Actions/Contact/SubmitContactMessage.php`, request `app/Http/Requests/Contact/`, controller `Web/ContactController@store`. Guests must supply name and email; signed-in users supply only subject and message and the rest is filled from the account.

## Routes
`POST contact` (contact.store). Public.

## Frontend
Inline form on `pages/Support.vue` (no modal, no topic field).

## Guards
Throttle `contact-messages` (5/min per caller). Content sanitised.

## Admin
Nova `ContactMessage` resource, read-only (`ContactMessagePolicy`).

## Tests
`tests/Feature/Http/Controllers/Web/`.

## Open work
- No email notification to admins on submission; messages are only visible in Nova.
- No spam protection beyond the throttle (no honeypot or captcha).
