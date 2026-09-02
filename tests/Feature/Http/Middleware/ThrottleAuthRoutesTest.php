<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The six auth routes this application does not declare and therefore cannot
 * throttle on the route itself: Fortify registers four of them from its own
 * routes file and Nova registers two, and neither package has a limiter slot
 * for any of them. `config('fortify.middleware')` and `config('nova.middleware')`
 * are the only say we get, and App\Http\Middleware\ThrottleAuthRoutes is the
 * entry in both.
 *
 * `config('nova.middleware')` — **not** `config('nova.api_middleware')`, where
 * this middleware used to sit, and the difference is the whole reason
 * `nova.password.reset` is covered below. NovaCoreServiceProvider builds three
 * middleware groups out of those two arrays:
 *
 * - `nova` is `config('nova.middleware')`;
 * - `nova:api` is `config('nova.api_middleware')`, whose first entry is the
 *   `nova` group;
 * - `nova:auth` is `config('nova.middleware')` again, plus
 *   RedirectIfAuthenticated — it is built from the *middleware* array and never
 *   from `api_middleware`.
 *
 * Nova registers `nova.password.confirm` with `api_middleware` (inlined as an
 * array by PendingRouteRegistration) and `nova.password.reset` with `nova:auth`.
 * So an entry listed only in `api_middleware` reaches the confirm-password
 * route and can never reach the password reset one, which is exactly the state
 * this file used to document as correct. From `config('nova.middleware')` the
 * middleware reaches all three groups once.
 *
 * Every ceiling below is asserted against the *route*, not against the
 * middleware in isolation, because the thing that silently regresses is not the
 * class — it is the class being dropped from one of those two config arrays, or
 * moved back to the one that cannot reach `nova:auth`. A unit test of
 * `handle()` would still pass with `config/nova.php` reverted.
 */

/**
 * A wrong-password confirmation attempt on Fortify's `user/confirm-password`.
 *
 * Fortify answers a bad password with a redirect back and an invalid `password`
 * field, so a 429 is unambiguously the limiter rather than the credential
 * check: before this middleware, fifty of these returned fifty 422s and never a
 * 429, which is a clean yes/no password oracle behind a borrowed session cookie.
 */
function guessPassword(TestCase $test, User $user): TestResponse
{
    return $test->actingAs($user)
        ->from(route('password.confirm'))
        ->post(route('password.confirm.store'), ['password' => 'not-the-password']);
}

/**
 * A wrong-password confirmation attempt on Nova's copy of the same endpoint.
 *
 * Nova registers `nova.password.confirm` with `config('nova.api_middleware')`
 * and nothing else — but that array's first entry is the `nova` group, so the
 * route reaches ThrottleAuthRoutes through `config('nova.middleware')` like
 * every other Nova route does. It goes back to being a yes/no oracle — for an
 * *admin* password — the moment the middleware leaves that array.
 *
 * This used to be described as the load-bearing Nova case. It is not, and has
 * not been since the middleware moved: it passed while `nova.password.reset`
 * was still unthrottled, because `api_middleware` reached this route and not
 * that one. The reset test in `password resets` below is the one that pins the
 * config array.
 */
function guessAdminPassword(TestCase $test, Admin $admin): TestResponse
{
    return $test->actingAs($admin, 'admin')
        ->postJson('/nova/user-security/confirm-password', ['password' => 'not-the-password']);
}

/**
 * Submit a new password against Fortify's `reset-password` with a token that
 * was never issued.
 *
 * A miss is the shape that matters, and the load-bearing property is the
 * response: the broker answers with an invalid `email` field either way
 * (Fortify's FailedPasswordResetResponse turns the broker status into a
 * validation error), so a 429 here is unambiguously the limiter and not the
 * token check.
 *
 * What a rejected submission costs is worker time, not per-request CPU. Every
 * address below is `holder{n}@example.com`, which belongs to no user, so the
 * broker answers INVALID_USER out of its user lookup and never reaches the
 * token repository at all — **zero bcrypt on this path**. It still holds a PHP
 * worker for 200 ms, because the broker wraps its whole body in a Timebox whose
 * duration comes from `config('auth.timebox_duration')` — unset in
 * `config/auth.php`, so the framework default stands — and the early return out
 * of that window sits on the success path only. Unauthenticated, no session, no
 * cookie, any address: the exposure the ceiling is sized against is the worker
 * pool. Keep the justification on that number — .ai/rules/providers.md records
 * why an inflated per-request cost is what gets a limiter deleted by the next
 * reviewer who checks it.
 *
 * The address is a parameter because it is the one thing the key must ignore.
 */
function submitPasswordReset(TestCase $test, string $email): TestResponse
{
    return $test->from(route('password.reset', 'not-a-real-token'))
        ->post(route('password.update'), [
            'token' => 'not-a-real-token',
            'email' => $email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);
}

/**
 * The same submission against Nova's copy, which resets an *admin* password out
 * of the `admins` broker.
 *
 * This is the route `config('nova.api_middleware')` could not reach: Nova
 * registers it with the `nova:auth` group, which is built from
 * `config('nova.middleware')`. It is posted by URL rather than by name for the
 * same reason the confirm-password helper above is — the route belongs to Nova.
 */
function submitAdminPasswordReset(TestCase $test, string $email): TestResponse
{
    return $test->from('/nova/password/reset/not-a-real-token')
        ->post('/nova/password/reset', [
            'token' => 'not-a-real-token',
            'email' => $email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);
}

/**
 * Post a fresh registration as a guest.
 *
 * The logout and session flush between attempts are what make this the caller a
 * limiter keyed on the IP is meant to bound: Fortify signs a new account in, and
 * a signed-in caller would key on their own user id from the second request on
 * and never reach the guest ceiling. A script registering accounts keeps no
 * cookie jar, so this is the honest shape rather than a convenience.
 */
function registerFreshAccount(TestCase $test, int $attempt): TestResponse
{
    Auth::logout();
    $test->flushSession();

    return $test->post(route('register.store'), [
        'name' => 'Applicant '.$attempt,
        'email' => "applicant{$attempt}@example.com",
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
}

describe('password confirmations', function () {
    test('returns 429 on the sixth password confirmation in a minute', function () {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            guessPassword($this, $user)->assertInvalid(['password']);
        }

        guessPassword($this, $user)->assertTooManyRequests();

        expect(session('auth.password_confirmed_at'))->toBeNull();
    });

    /**
     * The second limit, and the one that decides whether a patient guesser is
     * merely slow or hopeless. Five a minute alone allows 300 an hour; twenty
     * an hour is the number in AppServiceProvider and this is what holds it
     * there. The minute bucket is stepped over rather than waited out, which is
     * exactly what a script does.
     */
    test('returns 429 on the twenty first password confirmation in an hour', function () {
        $this->freezeTime();
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        for ($minute = 0; $minute < 4; $minute++) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                guessPassword($this, $user)->assertInvalid(['password']);
            }

            $this->travel(1)->minutes();
        }

        guessPassword($this, $user)->assertTooManyRequests();
    });

    /**
     * What this pins is that the middleware reaches a Nova route at all, and
     * that the confirmation key resolves an identifier on the `admin` guard —
     * a guard `$request->user()` on the default one knows nothing about, and
     * this limiter runs ahead of Nova's own Authenticate. Worth having because
     * Nova's confirm-password pays out an *admin* password: a confirmation
     * unlocks the back office's own recovery codes and passkey registration.
     *
     * It is **not** the test that pins which of `config/nova.php`'s two arrays
     * the middleware is listed in — it passed for as long as the entry sat in
     * `api_middleware`, while `nova.password.reset` was unthrottled. That
     * question belongs to `returns 429 on nova password reset once fortify has
     * spent the shared minute` below, and to nothing else in this file.
     *
     * Only the minute ceiling is asserted here. The hour ceiling belongs to the
     * `password-confirmations` limiter, which the Fortify test above already
     * pins; the two things that are different about Nova are the attachment and
     * the guard, and this one settles both.
     */
    test('returns 429 on the sixth nova password confirmation in a minute', function () {
        $admin = Admin::factory()->create(['password' => Hash::make('correct-horse')]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            guessAdminPassword($this, $admin)->assertUnprocessable();
        }

        guessAdminPassword($this, $admin)->assertTooManyRequests();
    });
});

describe('registrations', function () {
    /**
     * POST `register` was bounded by nothing at all, which makes the
     * application's mail domain an unauthenticated relay: every one of these
     * sends a verification mail to an address the caller chose.
     */
    test('returns 429 on the sixth registration in a minute', function () {
        Notification::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            registerFreshAccount($this, $attempt)->assertRedirect();
        }

        registerFreshAccount($this, 6)->assertTooManyRequests();

        expect(User::query()->count())->toBe(5);
    });

    test('returns 429 on the twenty sixth registration in a day', function () {
        Notification::fake();
        $this->freezeTime();
        $attempt = 0;

        for ($minute = 0; $minute < 5; $minute++) {
            for ($within = 1; $within <= 5; $within++) {
                registerFreshAccount($this, ++$attempt)->assertRedirect();
            }

            $this->travel(1)->minutes();
        }

        registerFreshAccount($this, ++$attempt)->assertTooManyRequests();

        expect(User::query()->count())->toBe(25);
    });
});

describe('password reset links', function () {
    /**
     * `config('auth.passwords.users.throttle')` bounds resends per *email
     * address*, so a caller walking an address list was bounded by nothing —
     * each address is a fresh budget. This bounds the caller, which is the half
     * that was missing, so every address below is a different one.
     */
    test('returns 429 on the fourth password reset link in a minute', function () {
        Notification::fake();
        $users = User::factory()->count(4)->create();

        foreach ($users->take(3) as $user) {
            $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => $users->last()->email])
            ->assertTooManyRequests();

        Notification::assertSentTimes(ResetPassword::class, 3);
    });

    test('returns 429 on the sixteenth password reset link in an hour', function () {
        Notification::fake();
        $this->freezeTime();
        $users = User::factory()->count(16)->create();

        foreach ($users->take(15)->chunk(3) as $chunk) {
            foreach ($chunk as $user) {
                $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
            }

            $this->travel(1)->minutes();
        }

        $this->post(route('password.email'), ['email' => $users->last()->email])
            ->assertTooManyRequests();

        Notification::assertSentTimes(ResetPassword::class, 15);
    });
});

/**
 * The other end of the reset flow: spending a token rather than asking for one.
 *
 * `password-resets` is 5 a minute and 20 an hour keyed on the caller's IP
 * **alone**, through the shared unauthenticated-caller key in AppServiceProvider
 * that every guest auth route in this map uses — never rateLimitKey(). It covers
 * both `password.update` (Fortify, POST `reset-password`) and `nova.password.reset`
 * (Nova, POST `nova/password/reset`) out of one bucket. Nothing else in the
 * application shares a bucket across two packages, so the tests below pin the
 * sharing on purpose rather than working around it.
 */
describe('password resets', function () {
    /**
     * Two wrong keys are refused here, and the ceiling on its own refuses
     * neither.
     *
     * Every attempt names a different address, which is what makes this a test
     * of the *key* and not just of a ceiling. `ip|email` — the shape Fortify's
     * own `login` limiter uses, and the obvious thing for somebody to
     * "improve" this into — would hand out a fresh bucket per typed address and
     * return six invalid-email responses and no 429, putting the held worker
     * back to unbounded per caller.
     *
     * The signed-in caller at the end refuses the other one: rateLimitKey().
     * It produces the IP for a guest, so every guest assertion above it passes
     * unchanged if the limiter is reverted to it, and the separate
     * unauthenticated-caller key stops being load bearing. An authenticated
     * caller is where the two diverge — rateLimitKey() would key on the user id,
     * find an empty bucket and let the request through to `guest`'s redirect;
     * the IP key still refuses it. ThrottleAuthRoutes runs from Fortify's
     * middleware *group*, ahead of the route's own `guest`, so the hit lands
     * before RedirectIfAuthenticated and 429 beats 302 (.ai/rules/middleware.md
     * records that ordering as deliberate).
     */
    test('returns 429 on the sixth password reset in a minute, whatever address each one names', function () {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            submitPasswordReset($this, "holder{$attempt}@example.com")->assertInvalid(['email']);
        }

        submitPasswordReset($this, 'holder6@example.com')->assertTooManyRequests();

        $this->actingAs(User::factory()->create());

        submitPasswordReset($this, 'holder7@example.com')->assertTooManyRequests();
    });

    /**
     * The second limit. Five a minute alone allows 300 an hour; twenty an hour
     * is the number in AppServiceProvider and this is what holds it there. The
     * minute bucket is stepped over rather than waited out, which is what a
     * script does.
     *
     * `X-RateLimit-Limit` is asserted because a bare `assertTooManyRequests()`
     * cannot say *which* ceiling fired. ThrottleRequests checks the limits in
     * declared order and the exception carries the exceeded limit's
     * `maxAttempts`, so 20 is the hour tier and 5 would be the minute one.
     * Without it, deleting the hour tier outright leaves this test green.
     */
    test('returns 429 on the twenty first password reset in an hour', function () {
        $this->freezeTime();
        $attempt = 0;

        for ($minute = 0; $minute < 4; $minute++) {
            for ($within = 1; $within <= 5; $within++) {
                submitPasswordReset($this, 'holder'.(++$attempt).'@example.com')->assertInvalid(['email']);
            }

            $this->travel(1)->minutes();
        }

        submitPasswordReset($this, 'holder'.(++$attempt).'@example.com')
            ->assertTooManyRequests()
            ->assertHeader('X-RateLimit-Limit', 20);
    });

    /**
     * The one test in this file that pins which config array the middleware
     * lives in.
     *
     * Nova registers `nova.password.reset` with the `nova:auth` group, which
     * NovaCoreServiceProvider builds from `config('nova.middleware')` and never
     * from `config('nova.api_middleware')`. Revert `config/nova.php` to the
     * `api_middleware` entry and this route carries no limiter at all, so the
     * sixth request below answers with an invalid `email` instead of a 429 —
     * every other test in this file stays green.
     *
     * It is written as five Fortify submissions and one Nova submission rather
     * than six Nova ones because that also states the shared bucket: two
     * packages, one route each, one allowance between them. Six Nova posts
     * would pass just as well against a private per-route limiter.
     */
    test('returns 429 on nova password reset once fortify has spent the shared minute', function () {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            submitPasswordReset($this, "holder{$attempt}@example.com")->assertInvalid(['email']);
        }

        submitAdminPasswordReset($this, 'admin@example.com')->assertTooManyRequests();
    });

    /**
     * IP keyed means *per* IP, not global. Without this, a limiter that keyed
     * on a constant would pass every test above while locking the whole
     * internet out of password recovery five submissions at a time.
     */
    test('leaves another machine reset allowance alone', function () {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            submitPasswordReset($this, "holder{$attempt}@example.com")->assertInvalid(['email']);
        }

        submitPasswordReset($this, 'holder6@example.com')->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);

        submitPasswordReset($this, 'holder7@example.com')->assertInvalid(['email']);
    });
});

/**
 * The other half of the contract, and the cheap half to get wrong. The
 * middleware runs on every route in both groups — every Fortify endpoint and
 * every `nova-api/*` call — and must cost those an array lookup and nothing
 * else.
 *
 * Both routes below are the immediate neighbour of a route that *is* in the
 * map: `user/confirmed-password-status` sits beside `user/confirm-password` and
 * `nova/user-security/confirmed-password-status` beside Nova's copy. A map that
 * ever keyed on a URI prefix instead of a route name would catch them, and
 * twenty five requests is comfortably past the 20-an-hour ceiling the
 * neighbouring route carries.
 */
test('does not throttle a fortify route that is not in the map', function () {
    $user = User::factory()->create();

    for ($attempt = 1; $attempt <= 25; $attempt++) {
        $this->actingAs($user)->getJson(route('password.confirmation'))->assertOk();
    }
});

test('does not throttle a nova route that is not in the map', function () {
    $admin = Admin::factory()->create();

    for ($attempt = 1; $attempt <= 25; $attempt++) {
        $this->actingAs($admin, 'admin')
            ->getJson('/nova/user-security/confirmed-password-status')
            ->assertOk();
    }
});
