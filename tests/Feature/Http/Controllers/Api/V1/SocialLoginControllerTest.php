<?php

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use GuzzleHttp\Exception\TransferException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as ProviderUser;

/**
 * `POST api/v1/auth/social/{provider}`: the token half of social sign-in.
 *
 * `Socialite::fake()` cannot stand in here — its FakeProvider forwards
 * `userFromToken()` to the real driver, which would call Google — so the
 * driver is a Mockery mock that answers `stateless()->userFromToken()`.
 */
beforeEach(function () {
    config([
        'services.google.client_id'     => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
    ]);
});

/**
 * Make Google answer the token exchange with an identity, or refuse it.
 */
function fakeGoogleTokenExchange(ProviderUser|Throwable $answer): void
{
    $driver      = Mockery::mock(GoogleProvider::class);
    $driver->shouldReceive('stateless')->andReturnSelf();

    $expectation = $driver->shouldReceive('userFromToken')->with('provider-access-token');

    $answer instanceof Throwable ? $expectation->andThrow($answer) : $expectation->andReturn($answer);

    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
}

function googleTokenIdentity(): ProviderUser
{
    return (new ProviderUser)->map([
        'id'       => '1234567890',
        'nickname' => null,
        'name'     => 'Sara Ahmed',
        'email'    => 'sara@example.com',
        'avatar'   => null,
    ]);
}

/**
 * @return array<string, string>
 */
function socialLoginPayload(): array
{
    return ['token' => 'provider-access-token', 'device_name' => 'Pixel 9'];
}

test('registers a verified account and issues a token', function () {
    fakeGoogleTokenExchange(googleTokenIdentity());

    $response = $this->postJson(route('api.v1.auth.social', 'google'), socialLoginPayload());

    $response->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'sara@example.com');

    $user     = User::query()->where('email', 'sara@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->tokens()->where('name', 'Pixel 9')->exists())->toBeTrue();

    $this->assertDatabaseHas('social_accounts', [
        'user_id'     => $user->id,
        'provider'    => 'google',
        'provider_id' => '1234567890',
    ]);

    $this->withToken($response->json('token'))
        ->getJson(route('api.v1.me.show'))
        ->assertOk()
        ->assertJsonPath('email', 'sara@example.com');
});

test('issues a token for an account already linked to the identity', function () {
    $user = User::factory()->has(
        SocialAccount::factory()->provider(SocialProvider::Google)->state(['provider_id' => '1234567890']),
    )->create();
    fakeGoogleTokenExchange(googleTokenIdentity());

    $this->postJson(route('api.v1.auth.social', 'google'), socialLoginPayload())
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);

    expect(User::count())->toBe(1);
});

test('rejects a token the provider does not recognise on the token field', function () {
    fakeGoogleTokenExchange(new TransferException('invalid token'));

    $this->postJson(route('api.v1.auth.social', 'google'), socialLoginPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token' => 'The Google token could not be verified.']);

    expect(User::count())->toBe(0);
});

test('refuses a deactivated account at issuance', function () {
    User::factory()->inactive()->create(['email' => 'sara@example.com']);
    fakeGoogleTokenExchange(googleTokenIdentity());

    $this->postJson(route('api.v1.auth.social', 'google'), socialLoginPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'Your account has been deactivated.']);
});

test('requires a token and a device name', function () {
    $this->postJson(route('api.v1.auth.social', 'google'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'device_name']);
});

test('an unknown or unconfigured provider is a 404', function (string $provider) {
    $this->postJson("/api/v1/auth/social/{$provider}", socialLoginPayload())->assertNotFound();
})->with(['twitter', 'facebook']);
