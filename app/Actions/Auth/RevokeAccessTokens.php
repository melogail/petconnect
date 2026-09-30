<?php

namespace App\Actions\Auth;

use App\Models\User;

/**
 * Sign a token bearer out.
 *
 * - `handle()` revokes the token that made the current request — a logout
 *   from one phone.
 * - `handleOthers()` revokes every token *except* the current one — what a
 *   password change owes the account's other installs without signing the
 *   user out of the phone they just typed the new password on.
 * - `handleAll()` revokes every token the account holds — account deletion.
 *
 * The first two read `currentAccessToken()`, which is only a
 * PersonalAccessToken behind `auth:sanctum` on the `api` group (that group
 * carries no session middleware, so Sanctum can never hand back a
 * TransientToken there). They are not for the web group.
 */
class RevokeAccessTokens
{
    public function handle(User $user): void
    {
        $user->tokens()->whereKey($user->currentAccessToken()->getKey())->delete();
    }

    public function handleOthers(User $user): void
    {
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();
    }

    public function handleAll(User $user): void
    {
        $user->tokens()->delete();
    }
}
