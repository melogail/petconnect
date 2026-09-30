<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\IssueAccessToken;
use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\User\AccountResource;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

/**
 * Sign-up from the mobile app.
 *
 * The account is created by the same action Fortify's browser form uses
 * (App\Actions\Fortify\CreateNewUser, injected concretely for its `User`
 * return type), so name, email and
 * password are validated once, in one place, for both clients. Firing
 * `Registered` is what sends the verification mail — the same event Fortify's
 * own controller fires. The response is a token plus the account so the app
 * lands on the verify-email screen already signed in.
 *
 * No policy-governed model is acted on, so there is no `authorize()` call.
 */
class RegisterController extends Controller
{
    public function store(
        RegisterRequest $request,
        CreateNewUser $createNewUser,
        IssueAccessToken $issueAccessToken,
    ): JsonResponse {
        $user = $createNewUser->create($request->accountInput());

        event(new Registered($user));

        return response()->json([
            'token' => $issueAccessToken->handle($user, $request->deviceName()),
            'token_type' => 'Bearer',
            'user' => AccountResource::make($user),
        ], 201);
    }
}
