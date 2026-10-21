<?php

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as ProviderUser;

/**
 * The browser half of social sign-in: `social.redirect` and `social.callback`
 * (routes/web.php), the pages that offer them, and the Action behind the
 * callback (Actions\Auth\ResolveSocialUser).
 *
 * Google is configured for every test and Facebook deliberately is not, so the
 * same fixture covers both "a known provider works" and "a known but unset
 * provider is hidden and 404s". The provider itself is `Socialite::fake()`;
 * nothing here leaves the process.
 */
beforeEach(function () {
    config([
        'services.google.client_id'       => 'google-client-id',
        'services.google.client_secret'   => 'google-client-secret',
        'services.facebook.client_id'     => null,
        'services.facebook.client_secret' => null,
    ]);
});

/**
 * What Google hands back after consent. The email is mixed case on purpose:
 * `users.email` is matched and stored lower-cased.
 *
 * @param  array<string, mixed>  $overrides
 */
function googleIdentity(array $overrides = []): ProviderUser
{
    return (new ProviderUser)->map([
        'id'       => '1234567890',
        'nickname' => null,
        'name'     => 'Sara Ahmed',
        'email'    => 'Sara@Example.com',
        'avatar'   => null,
        ...$overrides,
    ]);
}

describe('the pages', function () {
    test('login and register list only the configured providers', function (string $routeName) {
        $this->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('socialProviders', 1)
                ->where('socialProviders.0', ['value' => 'google', 'label' => 'Google']));
    })->with(['login', 'register']);
});

describe('redirect', function () {
    test('sends the browser to the provider', function () {
        Socialite::fake('google');

        $this->get(route('social.redirect', 'google'))
            ->assertRedirect('https://socialite.fake/google/authorize');
    });

    test('an unknown provider is a 404', function () {
        $this->get('/auth/twitter/redirect')->assertNotFound();
    });

    test('a provider without credentials is a 404', function () {
        $this->get(route('social.redirect', 'facebook'))->assertNotFound();
    });

    test('a signed-in member is sent home instead', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('social.redirect', 'google'))
            ->assertRedirect('/');
    });
});

describe('callback', function () {
    test('registers a verified account, links the identity and signs in', function () {
        Socialite::fake('google', googleIdentity());
        Event::fake([Registered::class]);

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('home', absolute: false));

        $user = User::query()->where('email', 'sara@example.com')->sole();

        $this->assertAuthenticatedAs($user);
        expect($user->name)->toBe('Sara Ahmed')
            ->and($user->hasVerifiedEmail())->toBeTrue()
            ->and($user->password)->not->toBeEmpty();

        $this->assertDatabaseHas('social_accounts', [
            'user_id'     => $user->id,
            'provider'    => 'google',
            'provider_id' => '1234567890',
        ]);

        Event::assertDispatched(Registered::class, fn (Registered $event) => $event->user->is($user));
    });

    test('sends no verification mail to the account it just verified', function () {
        Socialite::fake('google', googleIdentity());
        Notification::fake();

        $this->get(route('social.callback', 'google'));

        Notification::assertNothingSent();
    });

    test('falls back to the mailbox name when the provider gives none', function () {
        Socialite::fake('google', googleIdentity(['name' => null, 'nickname' => '']));

        $this->get(route('social.callback', 'google'));

        expect(User::query()->where('email', 'sara@example.com')->sole()->name)->toBe('sara');
    });

    test('signs in the account already linked to the identity without writing', function () {
        $user = User::factory()->has(
            SocialAccount::factory()->provider(SocialProvider::Google)->state(['provider_id' => '1234567890']),
        )->create(['email' => 'someone-else@example.com']);
        Socialite::fake('google', googleIdentity());

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticatedAs($user);
        expect(User::count())->toBe(1)
            ->and(SocialAccount::count())->toBe(1);
    });

    test('links the identity to the member who registered with the same email', function () {
        $user = User::factory()->create(['email' => 'sara@example.com']);
        Socialite::fake('google', googleIdentity());

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticatedAs($user);
        expect(User::count())->toBe(1);
        $this->assertDatabaseHas('social_accounts', [
            'user_id'     => $user->id,
            'provider'    => 'google',
            'provider_id' => '1234567890',
        ]);
    });

    test('refuses a deactivated account and links nothing', function () {
        User::factory()->inactive()->create(['email' => 'sara@example.com']);
        Socialite::fake('google', googleIdentity());

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status', 'Your account has been deactivated.');

        $this->assertGuest();
        expect(SocialAccount::count())->toBe(0);
    });

    test('refuses an identity the provider returned no email for', function () {
        Socialite::fake('google', googleIdentity(['email' => null]));

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status', 'We could not read an email address from your Google account.');

        $this->assertGuest();
        expect(User::count())->toBe(0);
    });

    test('a failed handshake lands on the login page with an explanation', function () {
        Socialite::fake('google', fn () => throw new InvalidStateException);

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status', 'We could not sign you in with Google. Please try again.');

        $this->assertGuest();
    });

    test('a provider without credentials is a 404', function () {
        $this->get(route('social.callback', 'facebook'))->assertNotFound();
    });
});

describe('social-logins limiter', function () {
    test('returns 429 on the eleventh request in a minute from one address', function () {
        Socialite::fake('google');

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->get(route('social.redirect', 'google'))->assertRedirect();
        }

        $this->get(route('social.callback', 'google'))->assertTooManyRequests();
    });
});
