<script setup lang="ts">
import { BellOff, CheckCheck, RefreshCw, Trash2 } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import NotificationSkeleton from '@/components/notifications/NotificationSkeleton.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { useNotificationInbox } from '@/composables/useNotificationInbox';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The inbox itself: the two bulk actions, a page of rows, and whatever stands
 * in for them.
 *
 * Every ref it reads lives in `useNotificationInbox`, which is module-scoped —
 * this component holds no state of its own, so it can be mounted inside the
 * bell's sheet, thrown away when the sheet closes and mounted again without
 * re-fetching anything.
 *
 * ## Paging without leaving the page
 *
 * `notifications.index` answers JSON, so its `links[].url` is not somewhere a
 * reader can be sent — following it as a link would show them a JSON document.
 * `Pagination` therefore runs in `as="button"` mode and hands the url back for
 * `loadUrl()` to fetch. There is no `only` here for the same reason: no page
 * object, nothing to scope a partial reload to.
 *
 * ## Three empty states, not one
 *
 * "Still loading", "the request failed" and "there is genuinely nothing here"
 * look nothing alike to a reader, and collapsing them into one blank panel is
 * how a broken endpoint gets mistaken for an empty inbox. The failure state is
 * the only one that offers a control, because it is the only one the reader can
 * do anything about — and its copy is English because `notifications.*` has no
 * key for it (see the phase report; `lang/` is not the frontend's to write).
 */
const {
    notifications,
    links,
    loading,
    failed,
    hasUnread,
    currentPage,
    load,
    loadUrl,
    markAsRead,
    markAllAsRead,
    deleteAll,
} = useNotificationInbox();

const { t } = useTranslations();
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <Button
                variant="ghost"
                size="sm"
                :disabled="!hasUnread"
                @click="markAllAsRead()"
            >
                <CheckCheck class="size-4" />
                {{ t('notifications.mark_all_as_read') }}
            </Button>

            <Button
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive"
                :disabled="notifications.length === 0"
                @click="deleteAll()"
            >
                <Trash2 class="size-4" />
                {{ t('notifications.delete_all') }}
            </Button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <NotificationSkeleton v-if="loading" />

            <EmptyState
                v-else-if="failed"
                :icon="RefreshCw"
                title="Could not load your notifications"
                description="The inbox did not answer. Check your connection and try again."
            >
                <Button variant="outline" size="sm" @click="load(currentPage)">
                    <RefreshCw class="size-4" />
                    Try again
                </Button>
            </EmptyState>

            <EmptyState
                v-else-if="notifications.length === 0"
                :icon="BellOff"
                :title="t('notifications.no_notifications_yet')"
                :description="t('notifications.empty_hint')"
            />

            <ul v-else class="space-y-1">
                <NotificationItem
                    v-for="notification in notifications"
                    :key="notification.id"
                    :notification="notification"
                    @read="markAsRead($event)"
                />
            </ul>
        </div>

        <Pagination
            v-if="!loading && !failed"
            :links="links"
            as="button"
            @navigate="loadUrl($event)"
        />
    </div>
</template>
