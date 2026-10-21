---
paths:
  - 'app/Actions/**'
---

# Actions

## Deleting a User bypasses every polymorphic cleanup Action — the account delete flow owns it
`users` has no SoftDeletes, and `pets`, `comments`, `reviews`, `likes`, `saves`, `reports`, `messages` and `conversation_user` all hang off `user_id`/`sender_id` with `cascadeOnDelete`. A DB cascade fires no Eloquent events, so `$user->delete()` runs none of `Actions\Reviews\DeleteReview`, `Actions\Comments\DeleteComment` or medialibrary's `deleting` hook for the cascaded rows.

Measured: A reviews B, C reports that review, A's account is deleted — the review is gone and C's report survives with `reportable` resolving to null. `comments.user_id` has the same hole, and worse: `comments.parent_id` cascades the descendants too, so the likes and reports of an entire subtree are stranded. `reviews.reviewable_id` / `likes.likeable_id` are morph columns with no FK, so deleting B strands every review and like *about* B as well. Same for the media, comments, likes, saves, reviews and reports of the deleted user's cascaded `pets`, and for their own `notifications` rows.

Rule: never delete a `User` with a bare `$user->delete()`. Go through `Actions\Profiles\DeleteUserAccount`, which collects the affected ids before the cascade can run and clears the polymorphic rows explicitly, all in one transaction. A new polymorphic child of `User` or of anything that cascades from `User` is a new step in that flow. `DeleteReview` and `DeleteComment` cover the single-row path only — do not read them as covering account deletion.

## Social sign-in (Google/Facebook): three settled decisions
Shipped 2026-10-11 (recorded by hand: the Boost `record-rule` tool was not connected in that session): laravel/socialite ^5.31, `App\Enums\SocialProvider` (driver names as backing values; `isConfigured()` = `services.{value}` has client_id AND client_secret), `social_accounts` (unique provider+provider_id, cascades from users, no tokens stored), `Actions\Auth\ResolveSocialUser` behind both `Web\SocialAuthController` (session) and `Api\V1\Auth\SocialLoginController` (stateless token exchange). Limiter `social-logins` (10/min, 60/hour, IP-keyed via `unauthenticatedCallerKey()`) on all three routes.

1. **`social.callback` is the ONE GET that writes** (user, link, session). OAuth 2.0 fixes the method and the provider's redirect is a top-level navigation, not an Inertia visit. What keeps it safe is that `SocialLoginButtons.vue` renders plain `<a>` tags, never `<Link>`, so nothing prefetches it. Do not cite this route as precedent for any other writing GET.

2. **A social-registered account gets `Str::password(32)`, NOT a nullable password.** `users.password` is NOT NULL and every settings flow (password change, account deletion, Fortify password confirmation, 2FA setup) takes `current_password`; such a member sets a password via "Forgot password?" first. Making the column nullable means touching all of those flows in one change — do it as its own piece of work, not in passing.

3. **Email match links by provider email** because Google and Facebook only return addresses they verified; a provider returning no email is refused (`ValidationException` on `email`), never guessed. New accounts are `markEmailAsVerified()`, and `Registered` is dispatched by the controller (not the Action) so the listener sends no verification mail. Social login bypasses a confirmed 2FA, the same as Fortify's passkey login — known and accepted.

Side effect found by the first test run: `User::create()` returned `is_active` as null in memory (column default only), so `isActive(): bool` threw a TypeError. Fixed per .ai/rules/factories.md with `protected $attributes = ['is_active' => true, 'locale' => 'en']` on `User`.
