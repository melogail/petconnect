<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RevokeAccessTokens;
use App\Actions\Profiles\UpdatePassword;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\Response;

/**
 * Change the password from the app.
 *
 * Same request and Action as Settings\SecurityController::update. The one
 * addition is revoking every *other* token: a password change is usually a
 * response to a lost or shared phone, and the browser flow gets the same
 * effect from `logoutOtherDevices`. This install keeps its token so the user
 * is not signed out by their own action.
 */
class PasswordController extends Controller
{
    use ResolvesRequestUser;

    public function update(
        PasswordUpdateRequest $request,
        UpdatePassword $updatePassword,
        RevokeAccessTokens $revokeAccessTokens,
    ): Response {
        $user = $this->actor($request);

        $updatePassword->handle($user, $request->newPassword());
        $revokeAccessTokens->handleOthers($user);

        return response()->noContent();
    }
}
