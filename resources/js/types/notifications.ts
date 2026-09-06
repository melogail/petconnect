import type { PaginationCursors, PaginationMeta } from '@/types/pagination';

/**
 * One row of the inbox — `App\Http\Resources\Notification\NotificationResource`.
 *
 * ## Nothing here is a sentence
 *
 * `message_key` and `message_replace` are a translation key and its
 * substitutions, and the backend calls `__()` on **neither** — a stored row
 * outlives the reader's locale, so a user who switches to Arabic has to see
 * their whole history in Arabic rather than text frozen at write time
 * (.ai/rules/notifications.md). `useTranslations().t(message_key,
 * message_replace)` is therefore the only thing that can render one of these,
 * and a notification list with no `t()` renders nothing but dotted keys.
 *
 * `created_at` is an ISO string for the same reason: the legacy payload carried
 * `diffForHumans()`, which is a locale and a reading time baked into a string
 * that goes stale while the panel is open.
 */
export type InboxNotification = {
    id: string;
    /**
     * The payload's own label, which the row's icon is keyed off.
     *
     * `like`, `comment`, `review`, `message` and `report` are what the five
     * notification classes write today, but the resource falls back to the
     * notification class's basename for a row written before the convention,
     * so this is an open set and must not be treated as exhaustive.
     */
    type: string;
    /** A key for `t()`, never a rendered sentence. */
    message_key: string;
    /** The `:name` / `:pet` / `:rate` substitutions that key expects. */
    message_replace: Record<string, string>;
    /** An absolute deep link, or null when the subject has no page. */
    url: string | null;
    /** The stored payload, whole — `pet_id`, `excerpt`, `rate`, and so on. */
    data: Record<string, unknown>;
    read: boolean;
    read_at: string | null;
    created_at: string;
};

/**
 * A page of `notifications.index`.
 *
 * Plain JSON, not an Inertia page object — the endpoint answers the bell's
 * `useHttp` fetch. `meta.unread_count` is the badge, and it is a count of the
 * whole mailbox rather than of this page: `BuildNotificationInbox` runs it as
 * its own query precisely so that "3 unread" cannot mean "3 unread on the slice
 * you are looking at".
 */
export type NotificationInboxPage = {
    data: InboxNotification[];
    /** Four navigation URLs. The numbered page buttons are `meta.links`. */
    links: PaginationCursors;
    meta: PaginationMeta & { unread_count: number };
};
