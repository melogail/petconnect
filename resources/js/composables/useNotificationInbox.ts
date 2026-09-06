import type { VisitOptions } from '@inertiajs/core';
import { router, useHttp, usePage } from '@inertiajs/vue3';
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
| The bell lives in a layout header. There used to be two of them —
| `AppSidebarHeader` for members and `PublicHeader` for the shell a guest can
| also reach — and moving between the two remounted the component; since the
| sidebar shell was removed (2026-09-06) `PublicHeader` is the only one, and a
| page-to-page visit keeps it mounted. Keeping the list and the badge out here
| still earns its place: a full document load is the one thing that does
| remount it, and this is what makes the cost of the badge **one request per
| full document load**, not one per page.
|
| That distinction is the point. `BuildNotificationInbox` and
| `HandleInertiaRequests::localeProps()` both record why the inbox is a route
| and not a shared prop: the legacy app ran an inbox query inside every single
| page render, feed filters and settings screens included. Fetching once per SPA
| session from the client is not that — every Inertia visit after the first
| costs nothing, and a reader with no account never fetches at all.
|
| The cost of holding it out here is that "one document load" is not the same
| span as "one reader". Signing out and signing in are Inertia **visits** —
| `UserMenuContent` posts `logout` through a `<Link>` and Fortify answers with
| `redirect()->intended()`, and there is no `Inertia::location` anywhere in
| `app/` to force a document load — so on a shared device the second reader's
| header would mount on top of the first reader's rows and unread badge.
| Caching by "has anything been fetched" leaks them.
|
| So the cache is keyed to the identity it was fetched for, not to a boolean:
| `cachedFor` below holds the `auth.user.id` the page was populated under, and
| a fetch happens whenever the current id differs from it — null included, so a
| sign-out empties the inbox too. Nothing has to remember to call a `reset()` on
| the way out, which is the point: a future login path cannot forget it.
| `useMessagingPreviews` is keyed the same way, for the same reason.
|
*/

const notifications = ref<InboxNotification[]>([]);
const links = ref<PaginationLink[]>([]);
const unreadCount = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const failed = ref(false);

/**
 * The `auth.user.id` the state above was fetched for, or null when it was
 * fetched for a guest. `undefined` means no fetch has been started yet, which
 * is why it is not simply `number | null`.
 */
let cachedFor: number | null | undefined;

/** Everything the inbox holds, back to the state a fresh document starts in. */
function discardCachedInbox(): void {
    notifications.value = [];
    links.value = [];
    unreadCount.value = 0;
    currentPage.value = 1;
    lastPage.value = 1;
}

/**
 * The signed-in reader this document is currently serving, or null for a guest.
 *
 * `usePage()` is a module-level accessor in Inertia v3 — it reads the router's
 * own page ref rather than `inject()`ing anything — so calling it outside a
 * component's setup is safe. `auth.user` is typed non-nullable and is null for
 * a guest (.ai/rules/types.md), hence the optional chain.
 */
function currentViewerId(): number | null {
    return usePage().props.auth.user?.id ?? null;
}

export type UseNotificationInboxReturn = {
    notifications: Ref<InboxNotification[]>;
    links: Ref<PaginationLink[]>;
    unreadCount: Ref<number>;
    currentPage: Ref<number>;
    lastPage: Ref<number>;
    loading: Ref<boolean>;
    failed: Ref<boolean>;
    hasUnread: ComputedRef<boolean>;
    /** Fetch a page of the inbox by number, replacing what is held. */
    load: (page?: number) => Promise<void>;
    /** Fetch a page by one of the paginator's own `links[].url`. */
    loadUrl: (url: string) => Promise<void>;
    /** Fetch page one, unless it was already fetched for this reader. */
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
 *
 * "On failure" means **every** failure, and the two transports reach that
 * differently. `markAsRead` is a `useHttp` call, so every non-2xx arrives as a
 * rejected promise and one `catch` covers the lot. The two `router` visits do
 * not have a single failure channel — see `optimisticVisitOptions` below, which
 * is where the interesting half of this lives.
 */
export function useNotificationInbox(): UseNotificationInboxReturn {
    /**
     * Two instances, because one `useHttp` carries one request's worth of
     * **form state** — `processing`, `progress`, `errors`, `response`, `isDirty`
     * and the defaults it resets against — and `submit()` resets all of it at
     * the top of every call. Marking a row read happens *while* a page fetch may
     * be in flight (opening the panel and clicking the first row is one gesture
     * apart), and on a shared instance the second call would clear and then
     * overwrite the first's state: `processing` would go false while the fetch
     * was still running, and `response` would hold whichever landed last.
     *
     * It is **not** about cancellation, which is what this docblock used to say.
     * `submit()` assigns a fresh `AbortController` without aborting the previous
     * one, so a shared instance would leave both requests in flight and only
     * tangle their state. The split is still right; the reason is shared state.
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
     * `notifications.index` is behind `auth` + `verified`, so a guest or
     * unverified account is redirected to login or the verification notice and
     * the XHR follows that to an **HTML document** that `JSON.parse` rejects.
     * `failed` goes true and the panel offers a retry — which for those two
     * readers can never succeed, so the retry must not be how they meet this.
     * It is not: `NotificationBell` gates render and mount fetch on `canRead`,
     * `auth.user?.email_verified_at != null`, false for a null viewer, so one
     * predicate covers both readers. The headers' `v-if="user"` is layout
     * grouping, not a second level of the guard. This branch is the backstop
     * for a session that expires under an open document.
     *
     * Every write that happens after the `await` is guarded on the identity the
     * request was *sent* for, so a response that arrives once the reader has
     * changed is dropped rather than painted over the new reader's inbox.
     *
     * The in-flight guard is conditional on the same thing. A second call for
     * the reader already being fetched is dropped — that is a second paginator
     * click landing on top of the first — while a call for a *different* reader
     * goes through, or an account switch would be swallowed by whatever the
     * previous reader left in flight.
     */
    async function fetchPage(url: string): Promise<void> {
        const viewerId = currentViewerId();

        if (loading.value && cachedFor === viewerId) {
            return;
        }

        if (cachedFor !== viewerId) {
            discardCachedInbox();
        }

        cachedFor = viewerId;
        loading.value = true;
        failed.value = false;

        try {
            const response = await inbox.get(url);

            if (currentViewerId() !== viewerId) {
                return;
            }

            notifications.value = response.data;
            links.value = response.meta.links;
            unreadCount.value = response.meta.unread_count;
            currentPage.value = response.meta.current_page;
            lastPage.value = response.meta.last_page;
        } catch {
            if (currentViewerId() !== viewerId) {
                return;
            }

            failed.value = true;
        } finally {
            if (currentViewerId() === viewerId) {
                loading.value = false;
            }
        }
    }

    function load(page = 1): Promise<void> {
        return fetchPage(notificationIndex.url({ query: { page } }));
    }

    function loadUrl(url: string): Promise<void> {
        return fetchPage(url);
    }

    /**
     * `cachedFor` gates this, and it is set at the *start* of `fetchPage()`, so
     * a request in flight counts as fetched and two headers mounting in the
     * same tick still make one request. A failed fetch counts too: the panel's
     * retry button is the way back from that, not a second mount.
     */
    async function ensureLoaded(): Promise<void> {
        if (cachedFor === currentViewerId()) {
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

    /**
     * The visit options both bulk writes share: optimistic on the way out, and
     * a re-fetch on the way back unless the server confirmed the write.
     *
     * ## Why `onError` alone cannot implement that policy
     *
     * In Inertia v3 `onError` fires **only** when the response is an Inertia
     * page whose props carry `errors` — the 422 case, and neither of these two
     * endpoints has anything to validate. Everything else takes a different
     * door: a 429 from `throttle:inbox-actions`, a 419 or a 500 is not an
     * Inertia response at all, so `Response.handleNonInertiaResponse()` routes
     * it to `onHttpException`; a dropped connection goes to `onNetworkError`;
     * and a visit interrupted by a navigation reaches none of the three.
     *
     * The 429 is not a double-click — `inbox-actions` is **60 a minute**
     * (.ai/rules/routes.md), so two requests come nowhere near it. It is
     * reachable because the bucket is **shared with `conversations.read`**,
     * which the thread page fires once per render rather than on a gesture, so
     * a reader working through messages can arrive at the bell with the bucket
     * already spent. And the 429 is only one of the doors: **419, 500 and
     * offline** reach this same gap without any limiter being involved. Do not
     * re-justify this on a rapid-clicking scenario and then find the limiter
     * makes it impossible — the defect does not depend on one.
     *
     * This is the v1 → v3 `invalid` → `httpException` rename, and it is quiet in
     * a way a removal would not have been: `onError` still exists and still
     * fires, just for a narrower set than an optimistic write needs. Wiring
     * rollback to it alone left the badge zeroed and the list emptied while the
     * server was unchanged, with nothing to put it right until the next full
     * document load. Established by reading `@inertiajs/core`'s `Response`
     * class, not by triggering a 429.
     *
     * So the callback that decides is `onFinish`, which runs on all of those
     * paths, and what it tests is whether `onSuccess` ran. Anything that is not
     * a confirmed success re-fetches — the file's policy applied to every
     * failure mode rather than to validation alone.
     *
     * `resync` and `onConfirmed` are parameters because the two writes recover
     * to different places: mark-all re-fetches the page the reader is looking
     * at, delete-all has no page left to be on.
     */
    function optimisticVisitOptions(
        resync: () => void,
        onConfirmed: () => void = () => {},
    ): VisitOptions {
        let confirmed = false;

        return {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                confirmed = true;
                onConfirmed();
            },
            /**
             * A rate-limited piece of inbox housekeeping is a recoverable
             * condition, not something to show the reader Laravel's 429 document
             * for. Returning false suppresses the overlay Inertia would
             * otherwise open over the page; the re-fetch below is what makes the
             * badge honest again. Every other status keeps Inertia's default
             * error surface — a 500 here is a bug somebody should see.
             */
            onHttpException: (response) => {
                if (response.status === 429) {
                    return false;
                }
            },
            onFinish: () => {
                if (!confirmed) {
                    resync();
                }
            },
        };
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
            optimisticVisitOptions(() => void load(currentPage.value)),
        );
    }

    /**
     * The optimistic clear drops the cache **identity** as well as the rows.
     *
     * `cachedFor` is what `ensureLoaded()` gates on, so leaving it set while the
     * rows were thrown away meant a failed delete could strand the inbox empty
     * for the rest of the session: the re-fetch is the first line of defence,
     * but if it also fails, nothing else asks for the inbox again — a remount of
     * the bell would decide it was already loaded. `undefined` is the
     * no-fetch-has-happened state, so a later mount asks.
     *
     * A confirmed success re-establishes it, which is what `onConfirmed` is for:
     * the inbox really is empty for this reader then, and a remount should not
     * spend a request finding that out. Guarded on the identity the delete was
     * *sent* for, like every other post-await write in this file, so a response
     * arriving after a sign-out does not claim the empty inbox for whoever is
     * signed in now.
     */
    function deleteAll(): void {
        const viewerId = currentViewerId();

        discardCachedInbox();
        cachedFor = undefined;

        router.delete(
            destroyAll.url(),
            optimisticVisitOptions(
                () => void load(),
                () => {
                    if (currentViewerId() === viewerId) {
                        cachedFor = viewerId;
                    }
                },
            ),
        );
    }

    return {
        notifications,
        links,
        unreadCount,
        currentPage,
        lastPage,
        loading,
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
