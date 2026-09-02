<?php

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A page of a user's notifications, newest first, plus the unread badge count.
 *
 * ## Two queries, and the count is not derived from the page
 *
 * The paginator issues its own COUNT and its own SELECT; the unread total is a
 * third, deliberately separate COUNT rather than `$page->where('read_at', null)
 * ->count()`. Counting the page would report "3 unread" when the reader has 40,
 * because the page is bounded — the badge has to be about the mailbox, not
 * about the slice being rendered.
 *
 * Measured flat at 3 queries whether the account holds 5 notifications or 500.
 *
 * That figure is Action-scoped and measured under phpunit.xml's
 * `SESSION_DRIVER=array`; a real request pays 2-3 more for the `sessions` and
 * `cache` tables while `.env` keeps the `database` drivers. See
 * .ai/rules/app.md.
 *
 * ## Why this is a route and not a shared Inertia prop
 *
 * The legacy NotificationInboxService::sharedPropsFor() ran on **every page
 * render**: 20 rows plus an unread count, serialised into the props of the home
 * feed, the pet form and the settings screen alike, whether or not anybody
 * opened the bell. Here it sits behind `notifications.index`, so a page that
 * never opens the menu costs no notification query at all, and the list pages
 * properly instead of being permanently truncated at 20.
 *
 * ## No eager load, and that is not an oversight
 *
 * `notifications.notifiable` is a morph pair, but nothing in the payload
 * dereferences it — the recipient is the user who asked, and every notification
 * writes self-contained identifiers into `data` precisely so a row can be
 * rendered without loading anything it points at (see
 * .ai/rules/notifications.md). NotificationResource emits `data` as stored, so
 * there is no relation for `whenLoaded()` to drop and no N+1 to eager load
 * away.
 *
 * Read through `$user->notifications()`, so the morph columns are filled from
 * the model and no `notifiable_type` value is written by hand — the alias trap
 * .ai/rules/app.md records.
 *
 * `latest()` orders by `created_at` only; `notifications.id` is a UUID, so the
 * `id DESC` tiebreak the integer-keyed lists use would order by a random string
 * rather than by insertion. Same-second rows are therefore unordered between
 * themselves, which is the honest position — there is no monotonic column to
 * break the tie with.
 */
class BuildNotificationInbox
{
    /**
     * @return array{
     *     notifications: LengthAwarePaginator<int, DatabaseNotification>,
     *     unread_count: int
     * }
     */
    public function handle(User $user, ?int $perPage = null): array
    {
        $perPage ??= (int) config('petconnect.notifications.inbox_per_page', 15);

        return [
            'notifications' => $user->notifications()->latest()->paginate($perPage),
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }
}
