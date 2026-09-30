<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resend the verification mail. The link inside it is the web `verification.verify`
 * route, so verifying stays a browser step; the app polls `api.v1.me.show` for
 * `email_verified_at` to notice it happened.
 */
class EmailVerificationNotificationController extends Controller
{
    use ResolvesRequestUser;

    public function store(Request $request): JsonResponse
    {
        $user = $this->actor($request);

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('Your email address is already verified.'), 'sent' => false]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => __('A new verification link has been sent to your email address.'), 'sent' => true]);
    }
}
