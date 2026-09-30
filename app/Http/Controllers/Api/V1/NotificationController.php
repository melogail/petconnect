<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\BuildNotificationInbox;
use App\Actions\Notifications\DeleteAllNotifications;
use App\Actions\Notifications\MarkAllNotificationsAsRead;
use App\Actions\Notifications\MarkNotificationAsRead;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The notification inbox, for the mobile app.
 *
 * Payloads carry `message_key` + `message_replace`, never rendered text
 * (.ai/rules/notifications.md), so the app renders them from its own
 * catalogue; `url` is the web deep link, which the app maps onto its own
 * routes from the `type` and the ids in `data`.
 *
 * No policy governs a notification row: the query is scoped to the caller's
 * own inbox by the Actions, so — like Web\NotificationController — there is
 * no `authorize()` call.
 */
class NotificationController extends Controller
{
    use ResolvesRequestUser;

    public function index(Request $request, BuildNotificationInbox $buildNotificationInbox): AnonymousResourceCollection
    {
        $inbox = $buildNotificationInbox->handle($this->actor($request));

        return NotificationResource::collection($inbox['notifications'])
            ->additional(['meta' => ['unread_count' => $inbox['unread_count']]]);
    }

    public function markAsRead(
        Request $request,
        string $notification,
        MarkNotificationAsRead $markNotificationAsRead,
    ): NotificationResource {
        return NotificationResource::make($markNotificationAsRead->handle($this->actor($request), $notification));
    }

    public function markAllAsRead(Request $request, MarkAllNotificationsAsRead $markAllNotificationsAsRead): Response
    {
        $markAllNotificationsAsRead->handle($this->actor($request));

        return response()->noContent();
    }

    public function destroyAll(Request $request, DeleteAllNotifications $deleteAllNotifications): Response
    {
        $deleteAllNotifications->handle($this->actor($request));

        return response()->noContent();
    }
}
