<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

/**
 * A valid login body. Overrides replace keys one for one.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function loginPayload(User $user, array $overrides = []): array
{
    return [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 9',
        ...$overrides,
    ];
}

describe('register', function () {
    test('creates the account, sends the verification mail and issues a token', function () {
        Notification::fake();

        $response = $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Sara',
            'email' => 'sara@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'iPhone 16',
        ]);

        $response->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'sara@example.com')
            ->assertJsonPath('user.is_verified', false);

        $user = User::query()->where('email', 'sara@example.com')->firstOrFail();

        expect($response->json('token'))->toBeString()->not->toBeEmpty()
            ->and($user->tokens()->where('name', 'iPhone 16')->exists())->toBeTrue();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    });

    test('validates through the same rules as the browser form', function () {
        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Sara',
            'email' => 'not-an-email',
            'password' => 'password',
            'password_confirmation' => 'different',
            'device_name' => 'iPhone 16',
        ])->assertInvalid(['email', 'password']);
    });

    test('requires a device name', function () {
        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Sara',
            'email' => 'sara@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertInvalid(['device_name']);
    });
});

describe('login', function () {
    test('issues a token for valid credentials', function () {
        $user = User::factory()->create();

        $response = $this->postJson(route('api.v1.auth.login'), loginPayload($user));

        $response->assertOk()
            ->assertJsonPath('user.id', $user->getKey())
            ->assertJsonMissingPath('two_factor_required');

        expect($response->json('token'))->toBeString()
            ->and($user->tokens()->where('name', 'Pixel 9')->count())->toBe(1);
    });

    test('the token authenticates the bearer', function () {
        $user = User::factory()->create();

        $token = $this->postJson(route('api.v1.auth.login'), loginPayload($user))->json('token');

        $this->withToken($token)
            ->getJson(route('api.v1.me.show'))
            ->assertOk()
            ->assertJsonPath('id', $user->getKey())
            ->assertJsonPath('email', $user->email);
    });

    test('rejects a wrong password on the email field', function () {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.login'), loginPayload($user, ['password' => 'wrong']))
            ->assertInvalid(['email']);

        expect($user->tokens()->count())->toBe(0);
    });

    test('refuses a deactivated account at issuance', function () {
        $user = User::factory()->inactive()->create();

        $this->postJson(route('api.v1.auth.login'), loginPayload($user))
            ->assertInvalid(['email']);

        expect($user->tokens()->count())->toBe(0);
    });

    test('asks for the second factor when the account has 2FA and no code was sent', function () {
        $user = User::factory()->withTwoFactor()->create();

        $this->postJson(route('api.v1.auth.login'), loginPayload($user))
            ->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonMissingPath('token');

        expect($user->tokens()->count())->toBe(0);
    });

    test('issues a token when the 2FA code is valid', function () {
        $user = User::factory()->withTwoFactor()->create();
        $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));

        $this->postJson(route('api.v1.auth.login'), loginPayload($user, ['code' => $code]))
            ->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true);

        expect($user->tokens()->count())->toBe(1);
    });

    test('rejects a wrong 2FA code on the code field', function () {
        $user = User::factory()->withTwoFactor()->create();

        $this->postJson(route('api.v1.auth.login'), loginPayload($user, ['code' => '000000']))
            ->assertInvalid(['code']);
    });

    test('accepts and consumes a recovery code', function () {
        $user = User::factory()->withTwoFactor(recoveryCodes: ['alpha-one', 'beta-two'])->create();

        $this->postJson(route('api.v1.auth.login'), loginPayload($user, ['recovery_code' => 'alpha-one']))
            ->assertOk()
            ->assertJsonStructure(['token']);

        expect($user->fresh()->recoveryCodes())->not->toContain('alpha-one')->toHaveCount(2);
    });
});

describe('logout', function () {
    test('revokes only the token that made the request', function () {
        $user = User::factory()->create();
        $phone = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $this->withToken($phone)->postJson(route('api.v1.auth.logout'))->assertNoContent();

        expect($user->tokens()->pluck('name')->all())->toBe(['tablet']);

        // The auth manager caches the resolved guard user between in-process
        // requests; a real client's next request starts from nothing.
        $this->app['auth']->forgetGuards();
        $this->withToken($phone)->getJson(route('api.v1.me.show'))->assertUnauthorized();
    });

    test('requires a token', function () {
        $this->postJson(route('api.v1.auth.logout'))->assertUnauthorized();
    });
});

describe('forgot password', function () {
    test('sends the reset mail for a known address', function () {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.password.email'), ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('sent', true);

        Notification::assertSentTo($user, ResetPassword::class);
    });

    test('answers 200 for an unknown address so accounts cannot be enumerated', function () {
        Notification::fake();

        $this->postJson(route('api.v1.auth.password.email'), ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('sent', false);

        Notification::assertNothingSent();
    });
});

describe('verification notification', function () {
    test('resends the mail to an unverified account', function () {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.auth.verification.send'))
            ->assertOk()
            ->assertJsonPath('sent', true);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    });

    test('does nothing for a verified account', function () {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.auth.verification.send'))
            ->assertOk()
            ->assertJsonPath('sent', false);

        Notification::assertNothingSent();
    });
});
