import { useHttp, usePage } from '@inertiajs/vue3';
import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { previews as conversationPreviews } from '@/routes/conversations';
import type { ConversationPreview, ConversationPreviewList } from '@/types';

/*
|--------------------------------------------------------------------------
| Shared state
|--------------------------------------------------------------------------
|
| Module scope, not component scope, for the same reason `useNotificationInbox`
| gives at length: the messages menu lives in a layout header, and a remount of
| that header — a full document load, now that `PublicHeader` is the only
| header (the member shell's `AppSidebarHeader` was removed 2026-09-06) — must
| neither throw the list away nor re-query for it. Holding the rows and the
| badge out here is what makes the badge cost **one request per full document
| load** rather than one per Inertia visit.
|
| That is the whole point of the arrangement, and it is what makes fetching this
| from the client cheaper than the legacy app's `messaging` shared prop.
| `ConversationController::previews` records the rest: the legacy shell built
| previews plus `unread_count` inside **every** page render, so the feed, the
| pet form and the settings screen each paid for an inbox nobody had opened.
| Here a page that never opens the menu costs one request for the badge, and a
| reader with no account costs nothing at all.
|
| ## The other side of that trade: the badge is accurate at document load, and
| ## only then
|
| The win above is bought with staleness, and the win must not be read without
| it. Legacy's `messaging` shared prop was **rebuilt inside every page render**,
| which is exactly what made it expensive — and exactly what made it correct
| after any navigation: a message that arrived while the reader was on the feed
| showed up on the next visit, because the next visit recomputed the count.
|
| This does not recompute. One fetch per document load and reader, gated on
| `cachedFor`, means **a message arriving mid-session never appears** — not on
| the next Inertia visit, not when the menu is opened, not ever, until something
| forces a full document load. There is no polling, no broadcast and no
| revalidation on open. The only two things that move the number after the
| initial fetch are the retry button and `markConversationRead`, and both move
| it **downwards**.
|
| So the honest statement of the arrangement is: cheaper than legacy, and less
| live than legacy. Both halves.
|
| `useNotificationInbox` has the identical shape — module-scoped state keyed on
| `auth.user.id`, one fetch per document load, no revalidation — so this is an
| **accepted application-wide trade**, not something this vertical invented and
| not something to "fix" here alone. Whoever decides the badges must be live
| decides it for both composables at once, and the fix is a mechanism neither
| has (polling on the menu, or a broadcast channel), not a cache key. It is
| written down rather than fixed because an accepted gap that nobody has
| recorded gets quietly worse: the next change that widens who sees a stale
| badge has nothing to collide with (.ai/rules/general.md, "A written gap is a
| tripwire").
|
| The cost of holding it out here is that "one document load" is not the same
| span as "one reader". Signing out and signing in are Inertia **visits** —
| `UserMenuContent` posts `logout` through a `<Link>` and Fortify answers with
| `redirect()->intended()`, and there is no `Inertia::location` anywhere in
| `app/` to force a document load — so on a shared device the second reader's
| header mounts on top of the first reader's peer names, avatars, snippets and
| unread badge. Caching by "has anything been fetched" leaks them.
|
| So the cache is keyed to the identity it was fetched for, not to a boolean:
| `cachedFor` below holds the `auth.user.id` the rows were populated under, and
| a fetch happens whenever the current id differs from it — null included, so a
| sign-out empties the menu too. Nothing has to remember to call a `reset()` on
| the way out, which is the point: a future login path cannot forget it.
|
*/

const previews = ref<ConversationPreview[]>([]);
const unreadCount = ref(0);
const loading = ref(false);
const failed = ref(false);

/**
 * The `auth.user.id` the state above was fetched for, or null when it was
 * fetched for a guest. `undefined` means no fetch has been started yet, which
 * is why it is not simply `number | null`.
 */
let cachedFor: number | null | undefined;

/**
 * Conversations the reader opened *before* this module had a snapshot to apply
 * the read to — the cache unresolved, or a fetch already in flight.
 *
 * They are replayed against the response inside `load()`, immediately after the
 * assignment, and cleared there. See `markConversationRead` for why the set is
 * needed and why it holds only that window.
 */
const readBeforeSnapshot = new Set<number>();

export type UseMessagingPreviewsReturn = {
    previews: Ref<ConversationPreview[]>;
    unreadCount: Ref<number>;
    loading: Ref<boolean>;
    failed: Ref<boolean>;
    hasUnread: ComputedRef<boolean>;
    /** Fetch the newest handful, replacing what is held. */
    load: () => Promise<void>;
    /** Fetch them, unless they were already fetched for this reader. */
    ensureLoaded: () => Promise<void>;
    /** Clear one row's unread state, and the badge with it. */
    markConversationRead: (conversationId: number) => void;
};

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

/**
 * The header messages menu's data: the newest handful of conversations and the
 * unread badge beside them.
 *
 * ## `useHttp`, not a router visit
 *
 * `conversations.previews` answers **plain JSON**, so an Inertia visit would
 * try to read a page object out of it and find none. `useHttp` is the built-in
 * XHR client Inertia v3 ships in place of axios; nothing here needs axios and
 * nothing should add it.
 *
 * There are no writes, `markConversationRead` included. Opening the menu
 * changes nothing on the server, and opening a thread is already written by the
 * thread page — `pages/messaging/Show.vue` posts `conversations.read` on mount,
 * which is why `ConversationController::show` is a pure read
 * (.ai/rules/controllers.md). So this composable is one `useHttp` instance and
 * one request shape, where the notification inbox needs two of each.
 *
 * ## Nothing paginates
 *
 * The endpoint returns `{data, meta}` and no `links`, deliberately. There is no
 * page number to hold, no `loadUrl`, and no "load more" to build — the contract
 * is "the newest five" (`petconnect.messaging.preview_per_page`), and the badge
 * is `meta.unread_count`, which counts the viewer's whole inbox rather than
 * those five.
 *
 * ## A reader who cannot reach the endpoint lands in the catch, as a backstop
 *
 * `conversations.previews` is behind `auth` + `verified`, so a guest or an
 * unverified account is redirected to login or to the verification notice and
 * the XHR follows that to an **HTML document**, which `JSON.parse` rejects.
 * `failed` goes true and the menu offers a retry — which for those two readers
 * can never succeed, so the retry must not be how they meet this. It is not:
 * `MessagesDropdown` gates its render and its mount fetch on `canRead`,
 * `auth.user?.email_verified_at != null`, false for a null viewer, so that one
 * predicate covers both readers. The `v-if="user"` in `PublicHeader` is layout
 * grouping, not a second level of the guard. This branch is the backstop for a
 * session that expires under an open document.
 */
export function useMessagingPreviews(): UseMessagingPreviewsReturn {
    const inbox = useHttp<Record<string, never>, ConversationPreviewList>();

    /**
     * Every write that happens after the `await` is guarded on the identity the
     * request was *sent* for, so a response that arrives once the reader has
     * changed is dropped rather than painted over the new reader's header.
     *
     * The in-flight guard is conditional on the same thing. A second call for
     * the reader already being fetched is dropped — that is a double-tap on the
     * retry button — while a call for a *different* reader goes through, or an
     * account switch would be swallowed by whatever the previous reader left in
     * flight.
     */
    async function load(): Promise<void> {
        const viewerId = currentViewerId();

        if (loading.value && cachedFor === viewerId) {
            return;
        }

        if (cachedFor !== viewerId) {
            previews.value = [];
            unreadCount.value = 0;
            readBeforeSnapshot.clear();
        }

        cachedFor = viewerId;
        loading.value = true;
        failed.value = false;

        try {
            const response = await inbox.get(conversationPreviews.url());

            if (currentViewerId() !== viewerId) {
                return;
            }

            previews.value = response.data;
            unreadCount.value = response.meta.unread_count;

            /**
             * The response is a snapshot of the server's state at the moment it
             * was computed; the reads below happened after that moment, on this
             * client. Replaying them here — after the assignment, never before
             * — is what stops the badge counting the thread the reader is
             * currently looking at on a cold load.
             */
            for (const conversationId of readBeforeSnapshot) {
                applyConversationRead(conversationId);
            }

            readBeforeSnapshot.clear();
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

    /**
     * `cachedFor` gates this, and it is set at the *start* of `load()`, so a
     * request in flight counts as fetched and two headers mounting in the same
     * tick still make one request. A failed fetch counts too: the retry button
     * is the way back from that, not a second mount.
     */
    async function ensureLoaded(): Promise<void> {
        if (cachedFor === currentViewerId()) {
            return;
        }

        await load();
    }

    /**
     * Apply one read against whatever snapshot is currently held.
     *
     * Guarded on `unread`, so a row that the server already returned as read —
     * or one already cleared here — is a no-op. That guard is what makes the
     * replay in `load()` safe to run unconditionally: whichever of the two
     * (the fetch or the read) the server saw first, the badge moves by one at
     * most.
     *
     * The clamp is on the *adjustment*, not on the rendered number, which is
     * the pattern `components/pets/card/PetCardCommentButton.vue:76-85` records
     * for the comment offset: the fetched total is a snapshot from request time
     * while the reader's actions are now, so the two can legitimately disagree
     * and only the adjustment may be bounded.
     */
    function applyConversationRead(conversationId: number): void {
        const preview = previews.value.find(
            (candidate) => candidate.id === conversationId,
        );

        if (!preview?.unread) {
            return;
        }

        preview.unread = false;
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }

    /**
     * Clear one row, because the reader just opened it.
     *
     * No request: `pages/messaging/Show.vue` posts `conversations.read` on
     * mount, so the server write is already on its way and a second one would
     * only race it. What is missing without this is the *client* half — the
     * badge and the row tint are module state that no page render recomputes,
     * unlike the legacy `messaging` shared prop which was rebuilt on every one.
     *
     * ## The cold-load race, and why a set is needed rather than a subtraction
     *
     * The common way into a thread is a pasted link or a bookmark, i.e. a full
     * document load. Then this call arrives while the module has nothing to
     * apply it to: the header's own `GET /conversations/previews` is still in
     * flight (or has not started), `previews.value` is empty, `find()` misses
     * and the mark is lost. The response that lands a moment later was computed
     * **before** `POST /conversations/{id}/read` was written, so it counts the
     * conversation the reader is looking at — and, because nothing recomputes
     * module state, keeps counting it for the life of the document.
     *
     * That was measured on 2026-09-03 and the measurement is in
     * `pages/messaging/Show.vue`'s docblock: on `/conversations/1` the request
     * order was `GET /conversations/1`, `GET /conversations/previews`,
     * `POST /conversations/1/read`, and the badge stayed at `2 unread`.
     *
     * So an unappliable read is **held**, not dropped, and replayed against the
     * response inside `load()`. It is held only for the window where the
     * snapshot is provably older than the read — cache unresolved
     * (`cachedFor === undefined`) or a fetch in flight (`loading`) — and not
     * for the general "this id is not in the cached list" case. Outside that
     * window a later fetch is newer than the read and already reflects it,
     * while the conversation may meanwhile have received a **new** message and
     * become genuinely unread again; replaying then would clear a row the
     * reader has not seen.
     *
     * The set is dropped on an identity change in `load()` for the same reason
     * `cachedFor` is keyed on the reader: two participants of one conversation
     * can sign in on the same device, and one reader's pending read must not be
     * replayed onto the other's badge.
     */
    function markConversationRead(conversationId: number): void {
        if (cachedFor === undefined || loading.value) {
            readBeforeSnapshot.add(conversationId);
        }

        applyConversationRead(conversationId);
    }

    return {
        previews,
        unreadCount,
        loading,
        failed,
        hasUnread: computed(() => unreadCount.value > 0),
        load,
        ensureLoaded,
        markConversationRead,
    };
}
