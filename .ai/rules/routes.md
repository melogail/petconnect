---
paths:
  - 'routes/**'
---

# Routes

## Every mutating route carries a throttle; content-edits is the default home
As of the rate-limit sweep, every write route in routes/web.php and routes/settings.php carries a named limiter except `profile.destroy` (still open, flagged). Do not add a mutating route without one, and do not write "deliberately not throttled" — that comment is what the next person reuses to justify removing a limiter. The `pets.update` block used to argue ownership (PetPolicy) was ceiling enough; it is not — ownership bounds how many rows a caller touches, not how much CPU and disk one owned row can be made to burn.

Where a new route goes: single-row update/destroy/toggle on a row the caller owns -> `throttle:content-edits` (30/min, shared on purpose, no sizing decision to make). Inbox housekeeping the client fires rather than the user (read cursors, mark-all, clear-all) -> `inbox-actions` (60/min). Anything that stores an image or runs a medialibrary conversion -> `pet-listings` / `pet-listing-edits` / `profile-updates`, because with no queue worker the conversions run inside the request. Public unauthenticated write -> IP-keyed limiter (`locale-switches`), never rateLimitKey(), which silently degrades to the IP and hides the decision.

## Correction: profile.destroy is throttled now — no write route is left uncapped
The section above says every write route carries a named limiter "except `profile.destroy` (still open, flagged)". That exception is closed. `DELETE settings/profile` now carries `throttle:account-deletions` (10/min + 20/hour, keyed by `rateLimitKey()`), with the rationale block above the group in `routes/settings.php` and the sizing argument in `AppServiceProvider::configureRateLimiters()`.

Read the routing rule as unconditional now: **there is no uncapped mutating route in this application.** Adding one without a limiter is a regression, not a continuation of a known gap.

Why it is its own family rather than `content-edits`: it can only succeed once, it runs nine pipeline steps in one transaction across nine tables plus media files off disk (no filesystem rollback), and its `current_password` rule is a `Hash::check` oracle. Generous ceiling because the legitimate use is one request per account lifetime — what is bounded is a client retrying a *failing* delete.

## notifications.read stays on content-edits; read-all is the reason, not "who fires it"
Settled: `notifications.read` keeps `throttle:content-edits` (30/min). It is the odd member of that family — the only route in it fired once *per row in a list* rather than once per item page, and its two siblings `notifications.read-all` / `notifications.destroy-all` sit on `inbox-actions` at 60. Clearing a 20-row bell one row at a time spends two thirds of a bucket shared with comment, review, message and pet edits.

Do not defend the placement on a "who fires it / a person clicks that row" axis — that argument does not distinguish it from `conversations.read`, which is on `inbox-actions`. The argument that actually holds: `read-all` is the pressure valve. A user facing a full bell has a one-request way through, so per-row clicking is a preference, not the only path. If `read-all` ever leaves the UI, move `notifications.read` to `inbox-actions` with its siblings.

Also: `content-edits` is bounded to rows the caller owns by a **policy for nine of its ten routes**; `notifications.read` is bounded by the owning relation instead (no policy — someone else's id is a 404 from `firstOrFail()`, not a 403). Do not write "every one is bounded by a policy".

## The notifications.read correction is folded into routes/web.php now
Closes the trailing note on "notifications.read stays on content-edits; read-all is the reason" above — the comment block over the notifications group in routes/web.php no longer carries the old "who fires it" wording. Read that section as fully applied, not as outstanding work.

The block now states: `read` stays on `content-edits` (30/min) because `read-all` on `inbox-actions` is the pressure valve that makes per-row marking a preference rather than the only path; the revisit trigger is that if `read-all` ever leaves the UI, `read` moves to `inbox-actions` with its siblings; and the "a person clicks that row" argument is named as rejected, because it does not distinguish `read` from `conversations.read`.

General point worth keeping: when a rationale is corrected, rewrite the copy in the code, do not append beside it. A stale justification sitting next to a corrected one is how the correction gets reverted — this is the second instance this phase, after the `ConversationNotPermitted` contract.
