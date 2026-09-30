<?php

namespace App\Actions\Auth;

use App\Models\User;

/**
 * Mint the bearer token a mobile install keeps.
 *
 * One token per device, named after the device, so the account's token list
 * reads as a list of phones and revoking one signs out one install. Tokens
 * carry no abilities: authorization is the policies' job, decided per request
 * against the user, exactly as it is for a browser session.
 */
class IssueAccessToken
{
    public function handle(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}
