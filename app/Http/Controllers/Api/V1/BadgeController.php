<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\CountUnreadConversations;
use App\Actions\Notifications\CountUnreadNotifications;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The two numbers on the tab bar, in one cheap request the app can poll.
 */
class BadgeController extends Controller
{
    use ResolvesRequestUser;

    public function show(
        Request $request,
        CountUnreadConversations $countUnreadConversations,
        CountUnreadNotifications $countUnreadNotifications,
    ): JsonResponse {
        $user = $this->actor($request);

        return response()->json([
            'unread_conversations' => $countUnreadConversations->handle($user),
            'unread_notifications' => $countUnreadNotifications->handle($user),
        ]);
    }
}
