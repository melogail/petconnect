<?php

namespace App\Actions\Auth;

use App\Actions\Users\RegisterUser;
use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as ProviderUser;

/**
 * Turn an identity a provider has just vouched for into the SniffPal account
 * it belongs to, creating or linking one on the way.
 *
 * Three cases, decided in order:
 *
 * 1. The (provider, provider id) pair is already linked — that account, no
 *    writes.
 * 2. No link, but a member already registered with the provider's email —
 *    link the identity to that account and sign them in. Both providers only
 *    hand out an email they have verified themselves, so a match on it is
 *    the same proof our own verification mail gives; a provider that did not
 *    return one is refused rather than guessed at.
 * 3. Nobody — register a new account through the same Action the sign-up form
 *    uses, mark the email verified for the reason in (2), and link.
 *
 * The new account gets a random password rather than a null one. `users.
 * password` is NOT NULL, and the settings flows (password change, account
 * deletion, Fortify's password confirmation) all take `current_password`;
 * a member who signed up with Google and wants any of those sets a password
 * through "Forgot password?" first, which works unchanged. That is a known
 * rough edge, chosen over making the column nullable and touching every one
 * of those flows in the same change.
 *
 * A deactivated account is refused *here*, as AuthenticateWithPassword does,
 * because the API caller has no session for EnsureAccountIsActive to end.
 * Every refusal is a ValidationException on `email` so both the browser
 * controller (which flashes the message to the login page) and the API
 * controller (422) render it the same way.
 *
 * `Registered` is NOT dispatched here: the caller checks
 * `$user->wasRecentlyCreated` and fires it, the same split
 * Actions\Fortify\CreateNewUser keeps with Fortify's controller. The listener
 * sends no verification mail to an already-verified account.
 */
class ResolveSocialUser
{
    public function __construct(private readonly RegisterUser $registerUser) {}

    public function handle(SocialProvider $provider, ProviderUser $identity): User
    {
        $providerId = (string) $identity->getId();

        $linked     = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->with('user')
            ->first();

        $user       = $linked->user ?? $this->findOrRegister($provider, $identity);

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => __('Your account has been deactivated.')]);
        }

        if ($linked === null) {
            $user->socialAccounts()->create([
                'provider'    => $provider,
                'provider_id' => $providerId,
            ]);
        }

        return $user;
    }

    /**
     * The account behind the provider's email, registered if there is none.
     */
    protected function findOrRegister(SocialProvider $provider, ProviderUser $identity): User
    {
        $email    = Str::lower(trim((string) $identity->getEmail()));

        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => __('We could not read an email address from your :provider account.', ['provider' => $provider->label()]),
            ]);
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            return $existing;
        }

        $user     = $this->registerUser->handle([
            'name'     => $this->displayName($identity, $email),
            'email'    => $email,
            'password' => Str::password(32),
        ]);

        $user->markEmailAsVerified();

        return $user;
    }

    /**
     * The best name the provider gave us, falling back to the mailbox name so
     * `users.name` (NOT NULL) is never empty.
     */
    protected function displayName(ProviderUser $identity, string $email): string
    {
        $candidates = [
            trim((string) $identity->getName()),
            trim((string) $identity->getNickname()),
            Str::before($email, '@'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return Str::limit($candidate, 255, '');
            }
        }

        return $email;
    }
}
