<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\IssueAccessToken;
use App\Actions\Auth\ResolveSocialUser;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Http\Resources\User\AccountResource;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * Token login with a Google or Facebook access token.
 *
 * The mobile app runs the provider's SDK on the device and posts the access
 * token it got back; Socialite asks the provider who the token belongs to
 * (`stateless()`, because there is no session to hold OAuth state) and the
 * same Action the browser callback uses finds, links or registers the
 * account. A token the provider rejects is a 422 on `token`, the field the
 * client just sent.
 *
 * A new account answers 201, an existing one 200, both with the bearer token
 * and the account exactly as `auth.login` and `auth.register` answer.
 */
class SocialLoginController extends Controller
{
    public function store(
        SocialLoginRequest $request,
        SocialProvider $provider,
        ResolveSocialUser $resolveSocialUser,
        IssueAccessToken $issueAccessToken,
    ): JsonResponse {
        abort_unless($provider->isConfigured(), 404);

        /** @var AbstractProvider $driver Every SocialProvider case is an OAuth 2 driver; the facade only promises the Provider contract, which has no stateless(). */
        $driver = Socialite::driver($provider->value);

        try {
            $identity = $driver->stateless()->userFromToken($request->token());
        } catch (TransferException) {
            throw ValidationException::withMessages([
                'token' => __('The :provider token could not be verified.', ['provider' => $provider->label()]),
            ]);
        }

        $user   = $resolveSocialUser->handle($provider, $identity);

        if ($user->wasRecentlyCreated) {
            event(new Registered($user));
        }

        return response()->json([
            'token'      => $issueAccessToken->handle($user, $request->deviceName()),
            'token_type' => 'Bearer',
            'user'       => AccountResource::make($user->loadMissing('media')),
        ], $user->wasRecentlyCreated ? 201 : 200);
    }
}
