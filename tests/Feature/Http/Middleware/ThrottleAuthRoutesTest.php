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
 * The four auth routes this application does not declare and therefore cannot
 * throttle on the route itself: Fortify registers three of them from its own
 * routes file and Nova registers the fourth, and neither package has a limiter
 * slot for any of them. `config('fortify.middleware')` and
 * `config('nova.api_middleware')` are the only say we get, and
 * App\Http\Middleware\ThrottleAuthRoutes is the entry in both.
 *
 * Every ceiling below is asserted against the *route*, not against the
 * middleware in isolation, because the thing that silently regresses is not the
 * class — it is the class being dropped from one of those two config arrays.
 * A unit test of `handle()` would still pass with `config/nova.php` reverted.
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
 * This is the load-bearing one. Nova registers `nova.password.confirm` with
 * `config('nova.api_middleware')` and nothing else, so it is the case that goes
 * back to being an oracle — for an *admin* password — the moment
 * ThrottleAuthRoutes is dropped from that array.
 */
function guessAdminPassword(TestCase $test, Admin $admin): TestResponse
{
    return $test->actingAs($admin, 'admin')
        ->postJson('/nova/user-security/confirm-password', ['password' => 'not-the-password']);
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
     * The whole reason `config/nova.php` names this middleware. Nova's
     * confirm-password pays out an *admin* password, and a confirmation unlocks
     * the back office's own recovery codes and passkey registration.
     *
     * Only the minute ceiling is asserted here. The hour ceiling belongs to the
     * `password-confirmations` limiter, which the Fortify test above already
     * pins; what is different about Nova is whether the middleware is attached
     * at all and whether the key resolves on a guard `$request->user()` knows
     * nothing about, and both of those are settled by this one.
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
