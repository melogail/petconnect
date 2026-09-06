<?php

namespace App\Http\Controllers\Web;

use App\Actions\Notifications\BuildNotificationInbox;
use App\Actions\Notifications\DeleteAllNotifications;
use App\Actions\Notifications\MarkAllNotificationsAsRead;
use App\Actions\Notifications\MarkNotificationAsRead;
use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;

/**
 * The notification inbox.
 *
 * Every action acts on `$request->user()`'s own notifications and on nothing
 * else, so there is no policy and no `$this->authorize()` — ownership is the
 * query, not a check after it. .ai/rules/controllers.md exempts exactly this
 * shape: "if an action loads, writes or exposes a model somebody else could
 * own, it authorizes", and none of these can. A NotificationPolicy would be a
 * second place to get "is this yours" wrong.
 *
 * ## `index` returns JSON, the writes redirect back
 *
 * The same split `comments.index` and `reviews.index` have. The bell menu is a
 * panel on whatever page the user is already on, so it fetches its list with
 * Inertia v3's `useHttp` rather than making the user leave; the paginator keeps
 * its `data`/`links`/`meta` envelope even though JsonResource::withoutWrapping()
 * is on application-wide (see .ai/rules/resources.md). The unread badge rides
 * along in `meta` through `additional()`, so opening the menu is one request
 * rather than two.
 *
 * The legacy arrangement was the opposite: NotificationInboxService ran on every
 * single page render and put 20 rows plus the count into the shared Inertia
 * props of the home feed, the pet form and the settings screen alike. A page
 * that never opens the bell now costs no notification query at all.
 *
 * ## `read` is a POST, and that is not pedantry
 *
 * Marking something read is a write, so it never rides on a GET. Under Inertia
 * v3 that is a correctness requirement rather than a purity argument: link
 * prefetching and instant visits issue real GET requests on hover and on
 * intent, so a read-on-render endpoint would clear the badge as the pointer
 * crossed the menu. Same reason `conversations.read` exists as its own POST —
 * see .ai/rules/controllers.md.
 */
class NotificationController extends Controller
{
    /**
     * A page of the viewer's notifications, newest first, with the unread total.
     */
    public function index(
        Request $request,
        BuildNotificationInbox $buildNotificationInbox,
    ): AnonymousResourceCollection {
        $inbox = $buildNotificationInbox->handle($request->user());

        return NotificationResource::collection($inbox['notifications'])
            ->additional(['meta' => ['unread_count' => $inbox['unread_count']]]);
    }

    /**
     * Mark one notification as read.
     *
     * The id is a UUID passed as a string rather than route-model-bound:
     * `notifications` is the framework's own table, and the Action scopes the
     * lookup to the viewer's relation so somebody else's id is a 404.
     */
    public function markAsRead(
        Request $request,
        string $notification,
        MarkNotificationAsRead $markNotificationAsRead,
    ): RedirectResponse {
        $markNotificationAsRead->handle($request->user(), $notification);

        return back();
    }

    /**
     * Mark every unread notification as read.
     */
    public function markAllAsRead(
        Request $request,
        MarkAllNotificationsAsRead $markAllNotificationsAsRead,
    ): RedirectResponse {
        $markAllNotificationsAsRead->handle($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notifications marked as read.')]);

        return back();
    }

    /**
     * Empty the inbox.
     *
     * `destroyAll`, at `DELETE notifications`, rather than a `destroy` that
     * takes no model: the verb says the method deletes the collection, and no
     * controller method in this application is registered at two URIs.
     */
    public function destroyAll(
        Request $request,
        DeleteAllNotifications $deleteAllNotifications,
    ): RedirectResponse {
        $deleteAllNotifications->handle($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notifications cleared.')]);

        return back();
    }
}
