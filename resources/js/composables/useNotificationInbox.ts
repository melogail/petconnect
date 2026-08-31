import { router, useHttp } from '@inertiajs/vue3';
import { computed, ref, type ComputedRef, type Ref } from 'vue';
import {
    destroyAll,
    index as notificationIndex,
    read as markRead,
    readAll,
} from '@/routes/notifications';
import type {
    InboxNotification,
    NotificationInboxPage,
    PaginationLink,
} from '@/types';

/*
|--------------------------------------------------------------------------
| Shared state
|--------------------------------------------------------------------------
|
| Module scope, not component scope, and that is the whole reason this is a
| composable rather than three refs inside `NotificationBell`.
|
| The bell lives in a layout header, and there are two of them —
| `AppSidebarHeader` for members and `PublicHeader` for the shell a guest can
| also reach. Moving between the two remounts the component. Keeping the list
| and the badge out here means a remount does not throw the inbox away and does
| not re-query for it, so the cost of the badge is **one request per full
| document load**, not one per page.
|
| That distinction is the point. `BuildNotificationInbox` and
| `HandleInertiaRequests::localeProps()` both record why the inbox is a route
| and not a shared prop: the legacy app ran an inbox query inside every single
| page render, feed filters and settings screens included. Fetching once per SPA
| session from the client is not that — every Inertia visit after the first
| costs nothing, and a reader with no account never fetches at all.
|
*/

const notifications = ref<InboxNotification[]>([]);
const links = ref<PaginationLink[]>([]);
const unreadCount = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const loading = ref(false);
/** One request has completed, successfully or not — used to gate the fetch. */
const loaded = ref(false);
const failed = ref(false);

export type UseNotificationInboxReturn = {
    notifications: Ref<InboxNotification[]>;
    links: Ref<PaginationLink[]>;
    unreadCount: Ref<number>;
    currentPage: Ref<number>;
    lastPage: Ref<number>;
    loading: Ref<boolean>;
    loaded: Ref<boolean>;
    failed: Ref<boolean>;
    hasUnread: ComputedRef<boolean>;
    /** Fetch a page of the inbox by number, replacing what is held. */
    load: (page?: number) => Promise<void>;
    /** Fetch a page by one of the paginator's own `links[].url`. */
    loadUrl: (url: string) => Promise<void>;
    /** Fetch page one, but only if nothing has ever been fetched. */
    ensureLoaded: () => Promise<void>;
    /** Mark one row read, on screen first and on the server after. */
    markAsRead: (notification: InboxNotification) => void;
    markAllAsRead: () => void;
    deleteAll: () => void;
};

/**
 * The bell's data: a page of notifications, the unread badge, and the four
 * things a reader can do to them.
 *
 * ## Two transports, and which endpoint gets which
 *
 * `notifications.index` answers **plain JSON**, so it is fetched with
 * `useHttp` — an Inertia visit would try to read a page object out of it. The
 * three writes answer `back()`, which is a redirect to whatever page the reader
 * is on, and they are split:
 *
 * - **`read`** goes through `useHttp` too, and the reason is cancellation. It
 *   fires as the reader follows the notification's deep link; an Inertia visit
 *   would be aborted the instant that navigation started, and the row would
 *   come back unread. `useHttp` requests sit outside the visit lifecycle
 *   entirely, so the two cannot race.
 *
 *   The cost is that `back()` answers a **redirect to an HTML page**, and
 *   `useHttp` does `JSON.parse(response.data)` on every 2xx body — so a
 *   *successful* mark-read rejects with a `SyntaxError`. That is why the catch
 *   below discriminates rather than treating any rejection as failure: a
 *   `SyntaxError` here means "the write landed and the body was a page", while
 *   a real refusal arrives as an `HttpResponseError` (`doRequest` rejects on
 *   status >= 400 before anything is parsed) and is worth resyncing from.
 * - **`read-all`** and **`destroy-all`** are `router` visits, because both
 *   attach `Inertia::flash('toast', …)` and a flash only reaches
 *   `initializeFlashToast()` through a real visit — which also means the
 *   redirect is followed with `X-Inertia` and comes back as a page object
 *   rather than as a document. Neither races anything: they are deliberate,
 *   they navigate nowhere, and `preserveState` keeps the panel open underneath.
 *
 * ## The unread count moves before the server confirms
 *
 * Every write updates the local rows first, so the dot goes out under the
 * pointer. On failure the whole page is re-fetched rather than rolled back by
 * hand: the inbox is small, the server is the authority on a count that a
 * second device may also have changed, and a hand-rolled rollback would be a
 * second place for the badge to go wrong.
 */
export function useNotificationInbox(): UseNotificationInboxReturn {
    /**
     * Two instances, because one `useHttp` carries one request's worth of
     * state: `processing`, `errors` and the cancellation of whatever it last
     * sent. Marking a row read happens *while* a page fetch may be in flight —
     * opening the panel and clicking the first row is one gesture apart — and
     * sharing an instance would have the two cancel each other.
     */
    const inbox = useHttp<Record<string, never>, NotificationInboxPage>();
    const write = useHttp<Record<string, never>, unknown>();

    /**
     * The one fetch, taking a URL because the paginator hands back URLs.
     *
     * `links[].url` is built by the paginator itself and already carries the
     * page and whatever else was on the query string, so following it is exact
     * in a way that pulling `?page=` out of it and rebuilding would not be.
     *
     * A guest or an unverified account is redirected to login or to the
     * verification notice, and the XHR follows that to an HTML document which
     * `JSON.parse` then rejects. It lands in the catch as a failure, which is
     * the honest answer: there is no inbox to show.
     */
    async function fetchPage(url: string): Promise<void> {
        if (loading.value) {
            return;
        }

        loading.value = true;
        failed.value = false;

        try {
            const response = await inbox.get(url);

            notifications.value = response.data;
            links.value = response.meta.links;
            unreadCount.value = response.meta.unread_count;
            currentPage.value = response.meta.current_page;
            lastPage.value = response.meta.last_page;
        } catch {
            failed.value = true;
        } finally {
            loaded.value = true;
            loading.value = false;
        }
    }

    function load(page = 1): Promise<void> {
        return fetchPage(notificationIndex.url({ query: { page } }));
    }

    function loadUrl(url: string): Promise<void> {
        return fetchPage(url);
    }

    async function ensureLoaded(): Promise<void> {
        if (loaded.value || loading.value) {
            return;
        }

        await load();
    }

    function markAsRead(notification: InboxNotification): void {
        if (notification.read) {
            return;
        }

        notification.read = true;
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);

        void write
            .post(markRead.url(notification.id))
            .catch((error: unknown) => {
                // A SyntaxError is `back()`'s HTML body meeting JSON.parse, which
                // means the write succeeded. Anything else is a real refusal.
                if (!(error instanceof SyntaxError)) {
                    void load(currentPage.value);
                }
            });
    }

    function markAllAsRead(): void {
        if (unreadCount.value === 0) {
            return;
        }

        const readAt = new Date().toISOString();

        notifications.value = notifications.value.map((notification) => ({
            ...notification,
            read: true,
            read_at: notification.read_at ?? readAt,
        }));
        unreadCount.value = 0;

        router.post(
            readAll.url(),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => void load(currentPage.value),
            },
        );
    }

    function deleteAll(): void {
        notifications.value = [];
        links.value = [];
        unreadCount.value = 0;
        currentPage.value = 1;
        lastPage.value = 1;

        router.delete(destroyAll.url(), {
            preserveScroll: true,
            preserveState: true,
            onError: () => void load(),
        });
    }

    return {
        notifications,
        links,
        unreadCount,
        currentPage,
        lastPage,
        loading,
        loaded,
        failed,
        hasUnread: computed(() => unreadCount.value > 0),
        load,
        loadUrl,
        ensureLoaded,
        markAsRead,
        markAllAsRead,
        deleteAll,
    };
}
