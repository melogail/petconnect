<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Resolve the account behind an email + password pair for the token API.
 *
 * Fortify owns the browser login and never issues tokens, so the API needs
 * its own credential check. It goes through the `web` guard's user provider
 * rather than `Hash::check()` directly so the hashing driver, rehash-on-login
 * and the `users` provider configuration stay in one place.
 *
 * Every refusal is a `ValidationException` on `email`, matching the message
 * Fortify shows a browser (`auth.failed`), so the client renders it under the
 * same field. A deactivated account is refused *here*, at issuance, because
 * `EnsureAccountIsActive` can only revoke a token that already exists — this
 * is clause 2 of the deactivation contract for a client that has no session.
 */
class AuthenticateWithPassword
{
    public function handle(string $email, string $password): User
    {
        $provider = $this->provider();

        $user = $provider->retrieveByCredentials(['email' => $email]);

        if (! $user instanceof User || ! $provider->validateCredentials($user, ['password' => $password])) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => __('Your account has been deactivated.')]);
        }

        $provider->rehashPasswordIfRequired($user, ['password' => $password]);

        return $user;
    }

    protected function provider(): UserProvider
    {
        return Auth::guard('web')->getProvider();
    }
}
