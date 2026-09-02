<?php

namespace App\Providers;

use App\MediaLibrary\MediaPathGenerator;
use App\MediaLibrary\OwnerDirectoryResolver;
use App\MediaLibrary\TemporaryDirectoryCleaningFileManipulator;
use App\Models\Admin;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Observers\MediaOwnerDirectoryObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Nova\Util;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * OwnerDirectoryResolver is scoped rather than resolved fresh: it memoises
     * the owner lookup per morph pair, and a request-lifetime instance keeps
     * that memo from outliving a request or a refreshed test database.
     * MediaPathGenerator is scoped for the same reason it always was —
     * medialibrary calls app() on it once per generated path — and now takes
     * the resolver rather than owning the lookup.
     *
     * The FileManipulator override is deliberately NOT scoped, and the contrast
     * with those two is the whole justification: they are scoped because they
     * hold a memo that has to live exactly one request and no longer, and this
     * one holds no state at all — it wraps a `finally` around the package's own
     * method and keeps nothing between calls. Binding it `scoped` or `singleton`
     * would advertise shared state that does not exist, and would hold one
     * instance across every job a queue worker runs for no gain. A plain bind
     * covers the inline path, PerformConversionsJob and
     * `media-library:regenerate` alike, because the package resolves the
     * manipulator from the container in all three.
     */
    public function register(): void
    {
        $this->app->scoped(OwnerDirectoryResolver::class);
        $this->app->scoped(MediaPathGenerator::class);
        $this->app->bind(FileManipulator::class, TemporaryDirectoryCleaningFileManipulator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureMediaOwnership();
        $this->configureRateLimiters();
    }

    /**
     * Define the named rate limiters the routes attach.
     *
     * Most limiters here guard a write that turns into a database notification
     * for somebody else, so an unthrottled tap loop is a notification flood
     * rather than a wasted write. The legacy app throttled nothing at all,
     * anywhere.
     *
     * Four other shapes are documented separately below: the writes that cost
     * image-conversion CPU rather than a row (`pet-listings`,
     * `pet-listing-edits`, `profile-updates`); the credential and mail flows
     * that Fortify and Nova register without a limiter slot, which are attached
     * by Http\Middleware\ThrottleAuthRoutes rather than on a route; the cheap
     * ceilings that exist so that no mutating route in routes/web.php is left
     * uncapped at all (`content-edits`, `inbox-actions`, `locale-switches`);
     * and the one terminal write (`account-deletions`).
     *
     * - `pet-likes` guards POST pets/{pet}/like.
     * - `comment-likes` guards POST comments/{comment}/like. Same shape and
     *   same budget as pet likes: it is the same gesture on a different model,
     *   and one shared limiter would let a like spree on a thread eat a
     *   visitor's allowance for the feed.
     * - `profile-likes` guards POST profile/{user}/like. Same shape and budget
     *   again, and its own limiter rather than a shared one for the same
     *   reason: a like spree on profiles must not eat the feed's allowance.
     * - `comments` guards POST comments/{type}/{id}. Far tighter, because a
     *   comment is public content that notifies the listing owner or the
     *   author being replied to, and because a human writes them one at a time
     *   — this is the ceiling on a script, not on a person.
     * - `messages` guards POST conversations/{conversation}/messages. Sized for
     *   a fast conversation rather than for a script: chat is bursty, and the
     *   recipient of the flood is one person who already agreed to the thread.
     * - `reviews` guards POST reviews/{type}/{id}. As tight as `comments` and
     *   for the same reasons — a review is public content that notifies the
     *   person it is about, and a human writes them one at a time. It is not
     *   tighter still despite the unique index on `reviews` allowing only one
     *   per target, because a script can walk targets: the index bounds how
     *   many reviews land on one profile, not how many a session can write.
     * - `reports` guards POST reports/{type}/{id}, and is the one limiter whose
     *   purpose is protecting the back office rather than another user's inbox.
     *   Every accepted report writes a notification row per moderator, so an
     *   unthrottled loop buries the queue the flow exists to fill. Sized below
     *   `reviews` because filing a report is a deliberate act nobody performs
     *   in bursts, and because the unique index means a genuine user cannot
     *   need to file two against the same target.
     * - `conversations` guards POST conversations, and is the tightest limiter
     *   in the app because it is the one that bounds *unsolicited* contact.
     *   Every other write here lands somewhere the reader chose; this one
     *   creates a new thread in a stranger's inbox. It carries two limits: 5 a
     *   minute stops a script, and 30 a day stops a patient one — a real user
     *   opening thirty new conversations in a day is already unusual, while a
     *   spammer needs thousands for the tactic to pay. Legacy had no throttle
     *   at all on conversation creation or on message sending.
     *
     * ## The cheap ceilings
     *
     * These three are not here because anything measured hurt. They are here
     * because "deliberately uncapped" is a claim that has to be re-argued every
     * time a route joins the group, and nobody re-argues it — they read the
     * comment and add the route.
     *
     * - `content-edits` is the one deliberately *shared* limiter in the
     *   application. It guards the ten update/destroy/toggle routes that change
     *   a row the caller already owns: `pets.destroy`, `pets.status.toggle`,
     *   `comments.update`, `comments.destroy`, `reviews.update`,
     *   `reviews.destroy`, `messages.update`, `messages.destroy`,
     *   `messages.pin` and `notifications.read`. The severity is honestly low —
     *   none of them notifies anybody, every one is bounded to rows the caller
     *   owns (by a policy for nine of them, by the owning relation for
     *   `notifications.read`: it has no policy on purpose, because
     *   MarkNotificationAsRead reads through `$request->user()`'s own relation,
     *   so somebody else's id is a 404 from firstOrFail() rather than a 403
     *   from a check that could be forgotten), and a loop rewrites a fixed set
     *   of rows rather than growing one. 30 a minute is above any human editing
     *   session and is a ceiling on a script. Sharing one bucket is the point
     *   rather than a shortcut: the eleventh route in this family is covered by
     *   naming the limiter, with no sizing decision to get wrong, and the
     *   routes it covers are things one person does one at a time so they do
     *   not compete.
     *
     *   `notifications.read` is the odd member of the group and stays here
     *   deliberately, so write down the reason rather than re-deriving it. It
     *   is the only route in the family fired once *per row in a list* instead
     *   of once per item page, so a user clearing a twenty-row bell one row at
     *   a time spends two thirds of a bucket shared with comment, review,
     *   message and pet edits — and its two siblings, `notifications.read-all`
     *   and `notifications.destroy-all`, sit on `inbox-actions` at 60. Do not
     *   defend the placement on a "who fires it" axis: that argument does not
     *   distinguish it from `conversations.read`, which is on `inbox-actions`.
     *   What makes 30 acceptable is that `read-all` exists — a user facing a
     *   full bell has a one-request pressure valve for exactly the case that
     *   would drain this bucket, so per-row clicking is a preference rather
     *   than the only way through. If `read-all` ever leaves the UI, move this
     *   route to `inbox-actions` with its siblings.
     * - `inbox-actions` guards the three writes that housekeep an inbox: POST
     *   conversations/{conversation}/read, POST notifications/read-all and
     *   DELETE notifications. 60 a minute, the loosest ceiling in this file,
     *   because these are fired by the client rather than by a deliberate act —
     *   `conversations.read` runs on every thread render — and a 429 here is an
     *   unread badge that will not clear. Its own bucket rather than
     *   `content-edits` for the usual reason: opening twenty threads must not
     *   spend the allowance for editing a comment. DELETE notifications sits
     *   here rather than on a tighter ceiling of its own because it is
     *   destructive and idempotent at the same time — an accidental client-side
     *   loop empties the list on the first pass and deletes nothing after it,
     *   so the ceiling exists to stop the loop, not to ration the user.
     * - `locale-switches` guards POST locale, the only unauthenticated write in
     *   the application that is not an auth flow. It reads as free and is not:
     *   `SESSION_DRIVER=database`, so every caller arriving without a cookie
     *   writes a `sessions` row, and the language picker is in the header of
     *   every public page. 60 a minute is far above a human changing language
     *   and low enough that a loop cannot fill the table.
     *
     * ## The terminal write
     *
     * - `account-deletions` guards DELETE settings/profile, the last mutating
     *   route in the application that carried no ceiling of any kind. It is the
     *   only limiter here on a write that can *succeed* exactly once: after it,
     *   the account the key counts against no longer exists.
     *
     *   Its own family rather than a share of `content-edits`, even though it
     *   is an authenticated destroy on a row the caller owns. `content-edits`
     *   is sized for edits that rewrite a fixed set of rows, notify nobody and
     *   are trivially repeatable; this one runs nine pipeline steps inside a
     *   single transaction, deleting across `pets`, `comments`, `reviews`,
     *   `likes`, `saves`, `reports`, `messages`, `conversation_user` and
     *   `notifications` and removing the media files of every listing the
     *   account owns — a filesystem half that no rollback undoes (see
     *   Actions\Profiles\DeleteUserAccount). Sharing the bucket would also let
     *   an afternoon of editing spend the allowance for leaving.
     *
     *   The ceiling is deliberately generous — 10 a minute, 20 an hour —
     *   because the legitimate use is one request in the lifetime of an
     *   account, so every number above 1 is already slack. What it bounds is a
     *   client or script retrying a *failing* delete: a rejected
     *   `current_password`, or a step that throws and rolls the whole
     *   transaction back after some files are already off disk. Two limits
     *   because the two costs decay differently — the minute bounds a burst of
     *   transactions and rollbacks, the hour bounds a patient loop.
     *
     *   The second thing it bounds is the `current_password` rule in
     *   DeleteProfileRequest, which is a `Hash::check` and therefore the same
     *   yes/no password oracle `password-confirmations` exists for, with a
     *   harsher payout on a hit. It is looser than that limiter's 5 a minute on
     *   purpose: this route sits behind `auth`, `verified` and
     *   UserPolicy::delete, so a guesser must already hold the victim's
     *   session, and at that point confirm-password is the cheaper oracle to
     *   attack. If that stops being true, tighten these numbers to match rather
     *   than adding a second limiter beside them.
     *
     * ## Image-conversion writes
     *
     * The three writes whose cost is CPU and disk on a web worker rather than a
     * row in a table, because `QUEUE_CONNECTION=database` has no worker deployed
     * and every conversion therefore runs inside the request. A policy cannot
     * bound any of this: owning the row a request rewrites says nothing about
     * how often it may be rewritten.
     *
     * - `pet-listings` guards POST pets. It is the heaviest write in the
     *   application — up to four images, each stored and put through two
     *   conversions — and it was the only one of the two heavy ones with no
     *   ceiling of any kind: measured, one account published 25 listings with
     *   real uploads in one burst, all 302. `comments` caps a text row at 10 a
     *   minute while this cost nothing to repeat. Two limits, because the two
     *   costs decay differently: 5 a minute bounds the CPU spike, and 30 an
     *   hour bounds the disk, while still leaving a rescue posting a hundred
     *   listings a day room to do it.
     * - `pet-listing-edits` guards PUT pets/{pet}, which costs exactly what
     *   `pets.store` costs: the same four images, each stored and put through
     *   the same two conversions, run **synchronously** because no queue worker
     *   is deployed. Ownership does not bound that — it bounds how many rows a
     *   caller can touch, not how much CPU and disk one owned row can be made
     *   to burn, and a single pet absorbs an unbounded re-upload loop. Sized
     *   one step above `pet-listings` at 10 a minute and 60 an hour because a
     *   real owner corrects a listing far more often than they publish one. Its
     *   own bucket rather than a share of `pet-listings`, so an afternoon of
     *   editing cannot stop an owner publishing; the worst case is therefore
     *   the sum of the two, which is still bounded.
     * - `profile-updates` guards PATCH settings/profile, for the same reason
     *   minus the durable half: an avatar upload runs two conversions and
     *   replaces the previous file, so a loop is CPU rather than storage. One
     *   limit at 10 a minute — a human saves a settings form once and then
     *   corrects a typo, not sixty times.
     *
     * ## Credential and mail flows
     *
     * These four are not attached on a route, because this application does
     * not declare the routes they guard — Fortify and Nova do, and neither
     * offers a limiter slot for them. Http\Middleware\ThrottleAuthRoutes is
     * what attaches them and carries the full reasoning.
     *
     * - `password-confirmations` guards POST user/confirm-password and Nova's
     *   POST nova/user-security/confirm-password. Unthrottled, both were a
     *   clean yes/no password oracle behind nothing but a session cookie, and
     *   a confirmation unlocks the two-factor recovery codes and passkey
     *   registration. 5 a minute matches Fortify's own `login` limiter, since
     *   it is the same act — "prove you know this password" — and 20 an hour is
     *   what makes a patient guesser hopeless rather than merely slow. A user
     *   who has genuinely forgotten their password has `password.request`.
     * - `registrations` guards POST register. Keyed on the caller's IP through
     *   unauthenticatedCallerKey(), chosen rather than inherited: the route is `guest`,
     *   and the IP is the only handle there is, because every field on the
     *   request is attacker-chosen — including the address the verification
     *   mail goes to. 5 a minute stops the script and 25 a day
     *   stops the patient one. That is deliberately per-IP even though a large
     *   NAT shares one: 25 sign-ups a day from a single address is already
     *   extraordinary, and the alternative — an unbounded mail relay pointed at
     *   third parties — costs the whole domain's deliverability. A CAPTCHA or
     *   WAF is the answer if a legitimate shared address ever hits it.
     * - `password-reset-links` guards POST forgot-password.
     *   `config('auth.passwords.users.throttle')` already bounds resends per
     *   *email address*; this bounds the *caller*, which is the half that was
     *   missing — walking an address list was 40 sends from one IP with no
     *   resistance. Keyed on the IP through unauthenticatedCallerKey() for the same
     *   reason `registrations` is, and with the same emphasis: the route is
     *   `guest` and the submitted address is attacker-chosen, so the caller is
     *   the only dimension worth counting. 3 a minute and 15 an hour: a real
     *   person mistypes their address once or twice.
     * - `password-resets` guards POST reset-password and Nova's POST
     *   nova/password/reset — the *submit* half of the flow, which was open
     *   while `password-reset-links` bounded the request half.
     *
     *   What justifies the ceiling is **worker time, not CPU**. This is the one
     *   number to keep: `PasswordBroker::reset()` wraps its whole body in
     *   `$this->timebox->call(..., $this->timeboxDuration)`, and
     *   PasswordBrokerManager reads that duration from
     *   `config('auth.timebox_duration', 200000)` — a key this application does
     *   not set, so the framework's 200,000 µs default stands. `Timebox::call()`
     *   sleeps out the remainder of that window unless `returnEarly()` was
     *   called, and `returnEarly()` sits *after* the reset callback, on the
     *   success path only. So every **rejected** submission pins a PHP worker
     *   for a fifth of a second, unauthenticated, with no session and no cookie,
     *   from any address. Thirty concurrent callers are thirty workers held; the
     *   exposure is the worker pool, not the CPU.
     *
     *   In queries a rejected POST is cheap, and saying so is part of the
     *   argument rather than a concession: one indexed `users` lookup
     *   (EloquentUserProvider on the submitted address), one primary-key read of
     *   `password_reset_tokens` (`email` is that table's primary key, so
     *   DatabaseTokenRepository::exists() reads a single row), and at most one
     *   bcrypt, since that method's `$record && ! expired && check` chain
     *   short-circuits before hashing on a missing or expired row. A limiter
     *   argued on per-request work would not survive review against those
     *   numbers; argued on the timebox, it does. The second half of the
     *   justification is the payout — this is the submit half of a credential
     *   flow that sets a password on a hit, an **admin** password on Nova's
     *   copy. Nova's NewPasswordController subclasses Fortify's and calls
     *   parent::store(), so both routes behave identically here.
     *
     *   Token guessing is not the exposure and never was, but describe the
     *   token correctly: DatabaseTokenRepository::createNewToken() returns
     *   `hash_hmac('sha256', Str::random(40), $hashKey)` — a 64-character *hex*
     *   string, not 64 characters of arbitrary alphabet — stored hashed again
     *   in a row that expires after `expire` minutes (60 for both brokers).
     *   The conclusion stands: guessing it is hopeless with or without this
     *   limiter.
     *
     *   One limiter for both routes on purpose: it is one act, and a shared
     *   bucket means attempts against the back office and the front office
     *   count together. The counter-argument was weighed rather than missed —
     *   admin resets are near-zero volume and pay out an admin credential, so a
     *   dedicated, tighter bucket for `nova.password.reset` would cost nothing
     *   in usability and would stop front-office traffic from ever being what
     *   leaves the admin half of the shared bucket spent. It is not split today
     *   only because two buckets is two numbers to keep in step for a route a
     *   handful of people ever touch; split it if the `admins` guard grows.
     *
     *   5 a minute and 20 an hour, the same numbers as `password-confirmations`,
     *   because it is the same act — "prove you may set this password" — and
     *   because a person whose new password keeps failing the policy (which is
     *   reached, since their token is valid) still has five tries a minute to
     *   satisfy it.
     *
     * Most of them are keyed by user id, falling back to the IP so a request
     * that somehow arrives unauthenticated is still bounded. Five are different
     * on purpose. `password-confirmations` combines the two (`user id|ip`)
     * because the account under attack and the machine attacking it are
     * different dimensions and both have to be bounded. `registrations`,
     * `password-reset-links` and `password-resets` key on the IP alone through
     * unauthenticatedCallerKey(), and `locale-switches` on `$request->ip()`
     * directly, because every one of those callers is unauthenticated by
     * construction and there is no account to count against.
     *
     * The first two of those three used to call rateLimitKey() and produced the
     * same value from it, because `register.store`, `password.email` and
     * `password.update` are all registered `guest:` by Fortify. That is exactly
     * what .ai/rules/routes.md forbids relying on: the *fallback* was what made
     * the key right, so nothing would have failed — not a test, not a static
     * check — if one of those routes ever stopped being `guest`. Routing them
     * through unauthenticatedCallerKey() makes the IP a decision instead of a
     * default. The value on the wire is unchanged for every caller a `guest`
     * route can actually serve.
     *
     * One asymmetry falls out of that, and it is **documented rather than
     * fixed, deliberately**: an *authenticated* caller POSTing to one of these
     * guest routes is counted against their IP rather than their user id,
     * because ThrottleAuthRoutes runs from the package middleware group ahead of
     * the route's own `guest`, so the hit is recorded before
     * RedirectIfAuthenticated turns them away. It has no consequence — neither
     * key lets them past the 302, and the request never reaches a controller
     * either way — so there is nothing to fix. Do not "correct" it by moving the
     * middleware or by asking the guard here: both change observable behaviour
     * (which bucket real traffic lands in) to tidy a case that cannot succeed.
     *
     * Every limiter with two limits prefixes its keys, because Laravel
     * evaluates limits in a shared bucket namespace and identical `by` values
     * would collide.
     *
     * Throttling lives here rather than in the publish pipeline on purpose: a
     * rate limit's only meaningful outcome is a 429 with Retry-After, which is
     * transport, and .ai/rules/pipelines.md keeps steps free of HTTP.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('pet-likes', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('comment-likes', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('profile-likes', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('comments', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('messages', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('reviews', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('reports', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('conversations', fn (Request $request): array => [
            Limit::perMinute(5)->by('minute:'.$this->rateLimitKey($request)),
            Limit::perDay(30)->by('day:'.$this->rateLimitKey($request)),
        ]);

        RateLimiter::for('content-edits', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('inbox-actions', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('locale-switches', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) $request->ip()));

        RateLimiter::for('account-deletions', fn (Request $request): array => [
            Limit::perMinute(10)->by('minute:'.$this->rateLimitKey($request)),
            Limit::perHour(20)->by('hour:'.$this->rateLimitKey($request)),
        ]);

        RateLimiter::for('pet-listings', fn (Request $request): array => [
            Limit::perMinute(5)->by('minute:'.$this->rateLimitKey($request)),
            Limit::perHour(30)->by('hour:'.$this->rateLimitKey($request)),
        ]);

        RateLimiter::for('pet-listing-edits', fn (Request $request): array => [
            Limit::perMinute(10)->by('minute:'.$this->rateLimitKey($request)),
            Limit::perHour(60)->by('hour:'.$this->rateLimitKey($request)),
        ]);

        RateLimiter::for('profile-updates', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('password-confirmations', fn (Request $request): array => [
            Limit::perMinute(5)->by('minute:'.$this->passwordConfirmationKey($request)),
            Limit::perHour(20)->by('hour:'.$this->passwordConfirmationKey($request)),
        ]);

        RateLimiter::for('registrations', fn (Request $request): array => [
            Limit::perMinute(5)->by('minute:'.$this->unauthenticatedCallerKey($request)),
            Limit::perDay(25)->by('day:'.$this->unauthenticatedCallerKey($request)),
        ]);

        RateLimiter::for('password-reset-links', fn (Request $request): array => [
            Limit::perMinute(3)->by('minute:'.$this->unauthenticatedCallerKey($request)),
            Limit::perHour(15)->by('hour:'.$this->unauthenticatedCallerKey($request)),
        ]);

        RateLimiter::for('password-resets', fn (Request $request): array => [
            Limit::perMinute(5)->by('minute:'.$this->unauthenticatedCallerKey($request)),
            Limit::perHour(20)->by('hour:'.$this->unauthenticatedCallerKey($request)),
        ]);
    }

    /**
     * Who a rate limit is counted against: the signed-in user, or the caller's
     * IP when there is none.
     */
    protected function rateLimitKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }

    /**
     * Who a password confirmation is counted against: the account whose
     * password is being guessed **and** the machine guessing it.
     *
     * Both halves are load bearing, which is why this does not reuse
     * rateLimitKey(). Keyed on the account alone, an attacker with two stolen
     * sessions gets two budgets; keyed on the IP alone, a shared NAT would lock
     * colleagues out of their own settings page.
     *
     * The guard has to be asked twice because the two routes this key serves
     * sit on different ones: Fortify's confirm-password is `web`, Nova's is
     * `config('nova.guard')` — `admin` here — and this limiter runs ahead of
     * Nova's own Authenticate middleware, so `$request->user()` on the default
     * guard is null for an admin. `guest` is the fallback that cannot happen on
     * either route (both are behind auth) and exists so the key is never the
     * bare IP by accident.
     */
    protected function passwordConfirmationKey(Request $request): string
    {
        $novaGuard = config('nova.guard');

        $identifier = $request->user()?->getAuthIdentifier()
            ?? $request->user(is_string($novaGuard) ? $novaGuard : null)?->getAuthIdentifier()
            ?? 'guest';

        return $identifier.'|'.$request->ip();
    }

    /**
     * Who an unauthenticated auth-flow write is counted against: the machine
     * that sent it, and nothing else.
     *
     * This is the key for all four of the `guest` routes ThrottleAuthRoutes
     * covers, across three limiters — POST register (`registrations`), POST
     * forgot-password (`password-reset-links`), and Fortify's POST
     * reset-password plus Nova's POST nova/password/reset (`password-resets`).
     * It is named for the caller it counts rather than for any one of those
     * flows on purpose. It previously carried the name of the password-reset
     * flow while already serving registration, and a name that asserts
     * something false about its own callers is believed by the next reader. Do
     * not rename it after whichever flow you arrived from; if a fifth guest
     * route needs a bucket, it belongs here too, under this name.
     *
     * Fortify registers every one of them with `guest:config('fortify.guard')`,
     * so there is no account to key on and rateLimitKey() would silently
     * degrade to exactly this value. This method exists so the key shape is a
     * decision rather than a fallback nobody notices when a route changes.
     *
     * The submitted email or username is deliberately **not** part of the key,
     * even though it is the obvious second dimension and is what Fortify's own
     * `login` limiter uses. Three reasons, in order of weight:
     *
     * 1. It is attacker chosen. Adding it hands a caller a fresh bucket for
     *    every address they type, so the thing actually being spent — a 200 ms
     *    Timebox on a PHP worker per rejected reset submission, an
     *    unauthenticated mail send per sign-up or link request, and unmetered
     *    attempts at an endpoint that pays out a password (an admin one on
     *    Nova's copy) — would go back to being unbounded per IP, which is the
     *    whole exposure these limiters exist for.
     * 2. Keyed on the address alone, or ahead of the IP, it becomes a weapon:
     *    a stranger could spend a victim's budget and lock them out of their
     *    own account recovery, which is worse than the attack it prevents.
     * 3. The per-account attack it would bound is not the realistic one. The
     *    token is a 64-character hex string — `hash_hmac('sha256',
     *    Str::random(40), $hashKey)` — stored hashed again in a row that
     *    expires in an hour, so guessing it is hopeless with or without a
     *    per-address ceiling, and a distributed guesser is not bounded by any
     *    key this application can compute.
     *
     * The collateral is a shared NAT: everybody behind one address shares the
     * ceiling — five reset submissions a minute, three link requests, five
     * sign-ups. On endpoints a person reaches once, from a link in their own
     * inbox or on the day they join, that is the right side to err on. A
     * CAPTCHA or WAF is the answer if a legitimate shared address ever hits it.
     */
    protected function unauthenticatedCallerKey(Request $request): string
    {
        return (string) $request->ip();
    }

    /**
     * Make every media row carry the directory its files are stored under.
     *
     * MediaPathGenerator builds `media/{owner directory}/...` and the owner
     * directory is a custom property on the row. The application's own upload
     * paths set it; nothing forced any *other* writer to, and the one that
     * mattered did not — Ebess\AdvancedNovaMediaLibrary's Media field fills
     * only the custom properties the field was configured with, which is
     * nothing for all four of our media fields, so every avatar and pet photo
     * an admin uploaded through Nova permanently lacked it and fell into the
     * generator's database fallback: two queries per owner per request, on the
     * public listing page.
     *
     * The observer closes it at the one point every writer passes through.
     * Configuring the four Nova fields with withCustomProperties() would have
     * fixed those four and nothing about the fifth uploader.
     */
    protected function configureMediaOwnership(): void
    {
        Media::observe(MediaOwnerDirectoryObserver::class);
    }

    /**
     * Map every morphable model to a short, stable alias.
     *
     * Polymorphic columns (likes, saves, comments, reviews, reports, media,
     * notifications) store these aliases instead of fully qualified class
     * names, so renaming or moving a model cannot orphan existing rows.
     *
     * The map is not only about the application's own polymorphic relations.
     * Because enforceMorphMap() is enforcing, every model Nova exposes as a
     * resource has to appear here too: Nova's ActionEvent writes
     * `actionable_type`, `target_type` and `model_type` as morph values, so the
     * first action run against an unmapped model throws
     * ClassMorphViolationException. `report` was the one missing entry when the
     * Report resource was added in Phase 3 — Report is the target of no
     * polymorphic relation in the application, only of Nova's action log. The
     * eight models below are exactly the eight Nova resources.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'admin' => Admin::class,
            'breed' => Breed::class,
            'category' => Category::class,
            'comment' => Comment::class,
            'pet' => Pet::class,
            'report' => Report::class,
            'review' => Review::class,
            'user' => User::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     *
     * JsonResource::withoutWrapping() is what keeps Inertia props readable: a
     * single resource arrives as the object itself rather than as {data: {...}},
     * while a paginated collection keeps its data/links/meta envelope, because
     * pagination metadata forces the wrapper back on regardless of this call.
     *
     * preventLazyLoading() is the guardrail that catches an N+1 while it is
     * being written rather than in production logs. It is on everywhere except
     * production, where a violation degrades a page instead of breaking it, and
     * except inside Nova, which lazy loads through its own field resolution and
     * is not code this application owns.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Model::preventSilentlyDiscardingAttributes(
            ! app()->isProduction(),
        );

        Model::preventLazyLoading(
            ! app()->isProduction(),
        );

        $this->configureLazyLoadingViolations();

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Let a lazy load inside Nova through, and throw on every other one.
     *
     * Nova resolves fields off models it did not eager load, so the guardrail
     * would fire on Nova's own internals rather than on application code. It is
     * scoped out per request instead of being switched off globally: the
     * prevention is worth far more on the four verticals still to be built than
     * a green /nova is worth losing it.
     *
     * The callback replaces Model::handleLazyLoadingViolation() wholesale, so it
     * has to restore that method's own early return for models that are not
     * persisted or were just created — those have no relation to load and the
     * framework never considers them a violation.
     */
    protected function configureLazyLoadingViolations(): void
    {
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            if (! $model->exists || $model->wasRecentlyCreated) {
                return;
            }

            if ($this->isNovaRequest()) {
                return;
            }

            throw new LazyLoadingViolationException($model, $relation);
        });
    }

    /**
     * Whether the current request is being served by Nova.
     *
     * Delegated to Nova's own matcher rather than re-derived from
     * `config('nova.path')`. Nova registers 110 routes and only 32 of them sit
     * under `nova/`: 76 are `nova-api/*` (every resource index, detail,
     * relatable and action field resolution — i.e. all the code that actually
     * lazy loads) and 2 are `nova-vendor/*`. A `$request->is($path, $path.'/*')`
     * check returned false for all 78 of those, so the scope-out covered the SPA
     * shell and nothing that renders a field. Util::isNovaRequest() knows all
     * three prefixes plus the `nova.domain` install, and tracks the package.
     *
     * ## `runningInConsole()` is not "there is no request to ask"
     *
     * It is `PHP_SAPI === 'cli'`, which is true for the whole test suite —
     * including a simulated HTTP request, which is a real request object routed
     * through the real kernel. A bare `runningInConsole()` early return
     * therefore made this method answer false for every `nova-api/*` request
     * under Pest, and the exemption was dead in exactly the place it needed
     * proving: `GET /nova-api/users` 500'd with LazyLoadingViolationException
     * out of the Avatar field's getMedia(). Live it looked fine, and the suite
     * agreed with it because Builder::hydrate() leaves the guard off below two
     * rows and every Nova fixture seeded one.
     *
     * `runningUnitTests()` is what separates the two: an artisan command, a
     * queue worker or a scheduler tick is console *and* not a test, and gets
     * the early return because its `request` is the placeholder
     * SetRequestForConsole builds from `config('app.url')`. A test is console
     * and a test, so the question is passed to Nova's own matcher against the
     * request the kernel actually bound — which answers false for the
     * application's own routes just as it does in the browser.
     */
    protected function isNovaRequest(): bool
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return false;
        }

        $request = $this->app->make('request');

        return $request instanceof Request && Util::isNovaRequest($request);
    }
}
