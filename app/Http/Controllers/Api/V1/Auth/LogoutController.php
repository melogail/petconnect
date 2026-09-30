<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RevokeAccessTokens;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Sign this install out: the token that made the request is revoked and
 * nothing else — the account's other phones keep theirs.
 */
class LogoutController extends Controller
{
    use ResolvesRequestUser;

    public function destroy(Request $request, RevokeAccessTokens $revokeAccessTokens): Response
    {
        $revokeAccessTokens->handle($this->actor($request));

        return response()->noContent();
    }
}
