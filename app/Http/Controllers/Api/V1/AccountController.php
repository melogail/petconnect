<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RevokeAccessTokens;
use App\Actions\Profiles\DeleteUserAccount;
use App\Actions\Profiles\UpdateProfile;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\DeleteProfileRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\User\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The signed-in account: read it, edit it, delete it.
 *
 * The API twin of Settings\ProfileController, on the same Form Requests and
 * Actions. `update` accepts multipart (the avatar is a file), so a client
 * sends it as `POST` with `_method=PATCH`. `destroy` revokes every token
 * before the account goes, so no other install is left holding a token for a
 * row that no longer exists.
 */
class AccountController extends Controller
{
    use ResolvesRequestUser;

    public function show(Request $request): AccountResource
    {
        $user = $this->actor($request);

        $this->authorize('view', $user);

        return AccountResource::make($user->loadMissing('media'));
    }

    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): AccountResource
    {
        $user = $this->actor($request);

        $this->authorize('update', $user);

        $updated = $updateProfile->handle(
            user: $user,
            attributes: $request->profileAttributes(),
            image: $request->uploadedImage(),
        );

        return AccountResource::make($updated->loadMissing('media'));
    }

    public function destroy(
        DeleteProfileRequest $request,
        RevokeAccessTokens $revokeAccessTokens,
        DeleteUserAccount $deleteUserAccount,
    ): Response {
        $user = $this->actor($request);

        $this->authorize('delete', $user);

        $revokeAccessTokens->handleAll($user);
        $deleteUserAccount->handle($user);

        return response()->noContent();
    }
}
