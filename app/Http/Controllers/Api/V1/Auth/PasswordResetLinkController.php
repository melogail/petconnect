<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

/**
 * "Forgot password" from the app.
 *
 * Sends the same reset mail Fortify sends; the link in it opens the web reset
 * page, which is the one place a password is reset. The response is the
 * broker's status message and is deliberately 200 whether or not the address
 * exists, so this endpoint cannot be used to enumerate accounts — the browser
 * form makes the same choice.
 */
class PasswordResetLinkController extends Controller
{
    public function store(PasswordResetLinkRequest $request): JsonResponse
    {
        $status = Password::broker(config('fortify.passwords'))->sendResetLink(['email' => $request->email()]);

        return response()->json([
            'message' => __(Password::RESET_LINK_SENT),
            'sent' => $status === Password::RESET_LINK_SENT,
        ]);
    }
}
