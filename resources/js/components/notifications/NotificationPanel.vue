<script setup lang="ts">
import { Bell, RefreshCw } from '@lucide/vue';
import { computed } from 'vue';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import NotificationSkeleton from '@/components/notifications/NotificationSkeleton.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { useNotificationInbox } from '@/composables/useNotificationInbox';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The inbox itself: a page of rows, whatever stands in for them, and the
 * paginator under it.
 *
 * Every ref it reads lives in `useNotificationInbox`, which is module-scoped —
 * this component holds no state of its own, so it can be mounted inside the
 * bell's sheet, thrown away when the sheet closes and mounted again without
 * re-fetching anything.
 *
 * The two bulk actions used to be here and are now `NotificationInboxActions`,
 * in the sheet header where legacy put them; that component's docblock has the
 * reasoning.
 *
 * ## Paging without leaving the page
 *
 * `notifications.index` answers JSON, so its `links[].url` is not somewhere a
 * reader can be sent — following it as a link would show them a JSON document.
 * `Pagination` therefore runs in `as="button"` mode and hands the url back for
 * `loadUrl()` to fetch. There is no `only` here for the same reason: no page
 * object, nothing to scope a partial reload to.
 *
 * Legacy had no paginator and closed the list with a "close and continue
 * browsing" link to the home page instead. That link is not reproduced: it
 * navigated away from whatever the reader was doing in order to dismiss a
 * panel that Escape, an outside click and the sheet's own close button already
 * dismiss without moving them. The paginator takes its place because this
 * endpoint genuinely pages and legacy's did not — legacy shipped the whole
 * inbox inside a shared prop on every page render.
 *
 * ## Three empty states, not one
 *
 * "Still loading", "the request failed" and "there is genuinely nothing here"
 * look nothing alike to a reader, and collapsing them into one blank panel is
 * how a broken endpoint gets mistaken for an empty inbox. The failure state is
 * the only one that offers a control, because it is the only one the reader can
 * do anything about.
 *
 * The empty state is legacy's shape — a filled circle holding the icon, then a
 * title and a hint, centred in the panel — rather than the shared
 * `EmptyState.vue`, whose dashed-border card is a page-level treatment that
 * reads as a dropped-out region inside a sheet. Same two strings as that
 * `EmptyState` call — `notifications.no_notifications_yet` and
 * `notifications.empty_hint` — but deliberately *not* the same icon: this
 * renders legacy's `Bell`, the icon in the empty branch of
 * `petconnect-old`'s `resources/js/components/web/NotificationsSheet.vue`, in
 * place of the `BellOff` the `EmptyState` call passed. The icon is the one
 * thing here that is parity with legacy rather than parity with the code it
 * replaces.
 *
 * The failure copy used to be an English literal, on the grounds that
 * `notifications.*` had no key for it. Phase 5 added the three it needed —
 * `notifications.could_not_load`, `notifications.could_not_load_hint` and
 * `common.try_again`, present in both catalogues — so every string on this
 * panel resolves through `t()` now.
 *
 * ## Which of the four states is on screen is also said out loud
 *
 * `NotificationSkeleton` is `aria-hidden`, correctly — three pulsing bars have
 * nothing to announce — but that left the panel mute: a reader opened the bell,
 * heard the sheet's title and description, and then heard nothing at all,
 * whichever way the fetch went, including when it failed. Two things fix that.
 * The scroll region carries `aria-busy` while a page is in flight, and one
 * `sr-only` `role="status"` line carries the sentence for whichever state is
 * showing.
 *
 * One live region, not a `role="status"` on each of the three blocks. A live
 * region announces changes made *while it is already in the document* far more
 * reliably than it announces its own insertion, and a single region that
 * outlives all four branches is in the document for every change this panel
 * makes, where per-block regions are inserted-with-content in every case —
 * the unreliable one — and would say the failed and empty copy twice, once as
 * the region, once as the visible block.
 *
 * Which changes those actually are is worth being exact about, because the
 * obvious answer is the wrong one. This is **not** mainly buying the
 * first `loading → loaded`: `SheetContent` has no `forceMount`, so this panel
 * is created when the sheet opens, long after `NotificationBell`'s
 * document-load fetch has settled. The region is therefore usually inserted
 * already holding its terminal sentence, which is the insertion case, not the
 * change case. What it genuinely buys is every transition that happens **while
 * the panel is open**: a paginator click (`loadUrl` → loading → loaded), the
 * retry button (failed → loading → loaded), and the two bulk actions'
 * resync — `markAllAsRead` re-loads the current page and `deleteAll` empties
 * the list to the empty sentence and then re-loads. Those are the ones a
 * reader is present for, and they are exactly the ones a mid-panel region
 * catches and an inserted-with-content one does not.
 *
 * That narrower account of what the region buys does not narrow what it costs,
 * and the cost is the same one the rejected per-block arrangement would carry:
 * a reader browsing line by line meets the failed and empty sentences twice. A
 * stale or silent panel is the worse trade.
 *
 * The loaded-with-rows case announces `notifications.notifications`, the name
 * of the thing that just arrived, because no catalogue key counts a page of
 * them and this pass added none. The other three are the same keys their
 * blocks render.
 */
/**
 * `navigate` is the one thing this component passes upward rather than
 * handling. A row's deep link is a navigation the surface *around* the panel has
 * to react to — the bell's sheet has to close, or the reader lands on the
 * destination behind an overlay — and the panel holds no reference to that
 * surface by design. `NotificationItem` records why the mark-read button does
 * not raise it.
 */
const emit = defineEmits<{ navigate: [] }>();

const {
    notifications,
    links,
    loading,
    failed,
    currentPage,
    load,
    loadUrl,
    markAsRead,
} = useNotificationInbox();

const { t } = useTranslations();

/**
 * `Pagination` renders nothing at all when there is one page, so the bordered
 * strip it sits in has to make the same decision or the panel closes with an
 * empty 1px rule under it. Same predicate as `Pagination`'s own `hasPages`.
 */
const hasPages = computed(
    () => links.value.filter((link) => link.url !== null).length > 1,
);

/**
 * The spoken counterpart of the four branches below, in the same order they are
 * tested in the template, so the two cannot disagree about which state is on.
 */
const inboxStatus = computed(() => {
    if (loading.value) {
        return t('common.loading');
    }

    if (failed.value) {
        return t('notifications.could_not_load');
    }

    if (notifications.value.length === 0) {
        return t('notifications.no_notifications_yet');
    }

    return t('notifications.notifications');
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <p role="status" class="sr-only">{{ inboxStatus }}</p>

        <div class="min-h-0 flex-1 overflow-y-auto" :aria-busy="loading">
            <div v-if="loading" class="px-6 py-4">
                <NotificationSkeleton />
            </div>

            <div
                v-else-if="failed"
                class="flex h-full min-h-64 flex-col items-center justify-center gap-3 px-6 text-center"
            >
                <div
                    class="bg-muted text-muted-foreground flex size-14 items-center justify-center rounded-full"
                >
                    <RefreshCw class="size-6" aria-hidden="true" />
                </div>
                <div class="space-y-1">
                    <p class="text-foreground text-sm font-medium">
                        {{ t('notifications.could_not_load') }}
                    </p>
                    <p class="text-muted-foreground text-xs">
                        {{ t('notifications.could_not_load_hint') }}
                    </p>
                </div>
                <Button variant="outline" size="sm" @click="load(currentPage)">
                    <RefreshCw class="size-4" />
                    {{ t('common.try_again') }}
                </Button>
            </div>

            <div
                v-else-if="notifications.length === 0"
                class="flex h-full min-h-64 flex-col items-center justify-center gap-3 px-6 text-center"
            >
                <div
                    class="bg-muted text-muted-foreground flex size-14 items-center justify-center rounded-full"
                >
                    <Bell class="size-6" aria-hidden="true" />
                </div>
                <div class="space-y-1">
                    <p class="text-foreground text-sm font-medium">
                        {{ t('notifications.no_notifications_yet') }}
                    </p>
                    <p class="text-muted-foreground text-xs">
                        {{ t('notifications.empty_hint') }}
                    </p>
                </div>
            </div>

            <ul v-else class="divide-border divide-y">
                <NotificationItem
                    v-for="notification in notifications"
                    :key="notification.id"
                    :notification="notification"
                    @read="markAsRead($event)"
                    @navigate="emit('navigate')"
                />
            </ul>
        </div>

        <div
            v-if="hasPages && !loading && !failed"
            class="border-border border-t px-6 py-3"
        >
            <Pagination
                :links="links"
                as="button"
                @navigate="loadUrl($event)"
            />
        </div>
    </div>
</template>
