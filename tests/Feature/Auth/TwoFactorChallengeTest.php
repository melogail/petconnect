<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/TwoFactorChallenge'),
        );
});

/**
 * Sign in far enough to be sitting on the challenge, which is where the login
 * flow parks an account with two factor confirmed.
 */
function reachTwoFactorChallenge(User $user): void
{
    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);

    test()->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    test()->assertGuest();
}

/**
 * The challenge had only ever been *rendered* here. Nothing in the suite had
 * ever completed one, so the half of two factor that decides whether a code is
 * accepted — the half a second factor exists for — was carried entirely by
 * Fortify being configured correctly, with no test to say so. A challenge that
 * waved every code through looks identical from the outside: the login redirects
 * to the challenge, the challenge renders, and the visitor ends up signed in.
 *
 * The secret has to be a real base32 key rather than the factory's `'secret'`
 * placeholder, because Google2FA derives the code from it.
 */
test('signs the visitor in for the current one time code', function () {
    $secret = app(Google2FA::class)->generateSecretKey();
    $user = User::factory()->withTwoFactor($secret)->create();

    reachTwoFactorChallenge($user);

    $this->post(route('two-factor.login'), [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertRedirect(config('fortify.home'));

    $this->assertAuthenticatedAs($user);
});

test('leaves the visitor signed out for a code that is not theirs', function () {
    $secret = app(Google2FA::class)->generateSecretKey();
    $user = User::factory()->withTwoFactor($secret)->create();

    reachTwoFactorChallenge($user);

    $this->from(route('two-factor.login'))
        ->post(route('two-factor.login'), ['code' => '000000'])
        ->assertInvalid(['code']);

    $this->assertGuest();
});

/**
 * The way back in when the authenticator app is on a lost phone. The code is
 * spent on use — a recovery code that survived would be a permanent second
 * password on a screenshot of a settings page.
 */
test('signs the visitor in for a recovery code and spends it', function () {
    $user = User::factory()
        ->withTwoFactor(recoveryCodes: ['recovery-code-1', 'recovery-code-2'])
        ->create();

    reachTwoFactorChallenge($user);

    $this->post(route('two-factor.login'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect(config('fortify.home'));

    $this->assertAuthenticatedAs($user);

    expect(json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true))
        ->not->toContain('recovery-code-1')
        ->toContain('recovery-code-2');
});

test('leaves the visitor signed out for a recovery code that is not theirs', function () {
    $user = User::factory()->withTwoFactor()->create();

    reachTwoFactorChallenge($user);

    $this->from(route('two-factor.login'))
        ->post(route('two-factor.login'), ['recovery_code' => 'not-a-code'])
        ->assertInvalid(['recovery_code']);

    $this->assertGuest();
});

/**
 * A one time code is six digits, so the challenge is the one screen in the
 * application where guessing is a viable attack: unthrottled, a million
 * attempts walks the whole space, and the code the visitor is holding stays
 * valid for a window. FortifyServiceProvider names a `two-factor` limiter of
 * five a minute, keyed by `login.id` rather than by IP so that spreading the
 * attempts across addresses does not reset it.
 *
 * That limiter is this application's configuration, not Fortify's default, and
 * nothing had exercised it. Asserted from a real sixth attempt rather than by
 * priming the RateLimiter, because the key it is bucketed under is the part
 * worth pinning.
 */
test('returns 429 on the sixth wrong code in a minute', function () {
    $user = User::factory()->withTwoFactor()->create();

    reachTwoFactorChallenge($user);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->from(route('two-factor.login'))
            ->post(route('two-factor.login'), ['code' => '000000'])
            ->assertInvalid(['code']);
    }

    $this->post(route('two-factor.login'), ['code' => '000000'])
        ->assertTooManyRequests();

    $this->assertGuest();
});
