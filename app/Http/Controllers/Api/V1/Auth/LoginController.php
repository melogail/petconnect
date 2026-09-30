<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\AuthenticateWithPassword;
use App\Actions\Auth\IssueAccessToken;
use App\Actions\Auth\VerifyTwoFactorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\User\AccountResource;
use Illuminate\Http\JsonResponse;

/**
 * Token login.
 *
 * Three outcomes, all decided by Actions:
 *
 * - bad credentials or a deactivated account → 422 on `email`
 *   (AuthenticateWithPassword);
 * - the account has 2FA and no `code` / `recovery_code` was sent → 200 with
 *   `two_factor_required: true` and no token, so the client asks for the code
 *   and posts the same form again (VerifyTwoFactorCode::required);
 * - otherwise → 200 with the bearer token and the account.
 *
 * Throttled by Fortify's `login` limiter (email|ip), the same bucket the
 * browser form spends, so a credential-stuffing run cannot double its
 * allowance by switching clients.
 */
class LoginController extends Controller
{
    public function store(
        LoginRequest $request,
        AuthenticateWithPassword $authenticateWithPassword,
        VerifyTwoFactorCode $verifyTwoFactorCode,
        IssueAccessToken $issueAccessToken,
    ): JsonResponse {
        $user = $authenticateWithPassword->handle($request->email(), $request->password());

        if ($verifyTwoFactorCode->required($user, $request->code(), $request->recoveryCode())) {
            return response()->json([
                'two_factor_required' => true,
                'message' => __('Enter the code from your authenticator app to continue.'),
            ]);
        }

        $verifyTwoFactorCode->handle($user, $request->code(), $request->recoveryCode());

        return response()->json([
            'token' => $issueAccessToken->handle($user, $request->deviceName()),
            'token_type' => 'Bearer',
            'user' => AccountResource::make($user->loadMissing('media')),
        ]);
    }
}
