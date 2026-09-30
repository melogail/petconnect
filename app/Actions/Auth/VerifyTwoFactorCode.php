<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * The second factor of a token login, for an account that has confirmed 2FA.
 *
 * Fortify's browser flow parks the login in the session and asks on a second
 * page; a token client has no session, so the login request carries the
 * one-time `code` (or a `recovery_code`) alongside the password. This Action
 * answers the three cases the controller has to distinguish:
 *
 * - `required()` — the account has 2FA and neither field was sent: the
 *   client is told to ask for the code and post again.
 * - a valid TOTP code, or an unused recovery code (which is consumed exactly
 *   as Fortify's own challenge consumes it): proceed.
 * - anything else: a 422 on `code`, the same field the client just rendered.
 */
class VerifyTwoFactorCode
{
    public function __construct(private readonly TwoFactorAuthenticationProvider $provider) {}

    public function required(User $user, ?string $code, ?string $recoveryCode): bool
    {
        return $user->hasEnabledTwoFactorAuthentication() && $code === null && $recoveryCode === null;
    }

    public function handle(User $user, ?string $code, ?string $recoveryCode): void
    {
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return;
        }

        if ($recoveryCode !== null) {
            $this->consumeRecoveryCode($user, $recoveryCode);

            return;
        }

        $secret = (string) decrypt($user->two_factor_secret);

        if ($code === null || ! $this->provider->verify($secret, $code)) {
            throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
        }
    }

    protected function consumeRecoveryCode(User $user, string $recoveryCode): void
    {
        $match = collect($user->recoveryCodes())
            ->first(fn (string $candidate): bool => hash_equals($candidate, $recoveryCode));

        if ($match === null) {
            throw ValidationException::withMessages(['recovery_code' => __('The provided two factor recovery code was invalid.')]);
        }

        $user->replaceRecoveryCode($match);
    }
}
