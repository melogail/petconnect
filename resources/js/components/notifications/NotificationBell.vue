<script setup lang="ts">
import { Bell } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import NotificationPanel from '@/components/notifications/NotificationPanel.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useNotificationInbox } from '@/composables/useNotificationInbox';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The bell in the header: the unread badge, and the sheet the inbox opens in.
 *
 * ## It is only ever rendered for a signed-in reader
 *
 * `notifications.*` is behind `auth` + `verified`, so a guest fetching it gets
 * a redirect to login. Both call sites gate on `auth.user` before rendering
 * this — `PublicHeader` inside its `v-if="user"`, `AppSidebarHeader` behind the
 * same check, because `auth.user` is typed non-nullable and is null for guests
 * anyway (.ai/rules/types.md). This component does not re-check; it is the
 * caller's job not to render a bell for somebody who has no inbox.
 *
 * ## Why the count is fetched on mount and not on open
 *
 * A badge that only appears after you click the thing it is supposed to be
 * telling you about is not a badge. `ensureLoaded()` is gated on module-scoped
 * state, so the fetch happens **once per full document load** rather than once
 * per mount: navigating between the public shell and the member shell remounts
 * this component and costs nothing, and every Inertia visit in between costs
 * nothing either.
 *
 * That is a different thing from what `BuildNotificationInbox` rejected. The
 * legacy app ran an inbox query inside every page render — every feed filter,
 * every settings screen — server-side and synchronously, ahead of the first
 * byte. This is one asynchronous request per session that blocks no paint.
 *
 * ## A sheet, not a dropdown menu
 *
 * The panel is a list with its own buttons, its own paginator and its own
 * scroll region. `DropdownMenu` gives every child menu-item semantics and
 * closes on any activation inside it, so "mark all as read" would shut the
 * inbox it had just changed. A sheet is a plain dialog: it stays open, it traps
 * focus properly, and on a phone it is the full-height surface a list of
 * notifications wants anyway.
 */
const { unreadCount, hasUnread, ensureLoaded } = useNotificationInbox();

const { t } = useTranslations();

const open = ref(false);

onMounted(() => void ensureLoaded());

/**
 * Two-digit ceiling, because the badge sits on a 36px control and a four-digit
 * count would push it off the header.
 */
const badge = computed(() =>
    unreadCount.value > 99 ? '99+' : unreadCount.value,
);

const label = computed(() =>
    hasUnread.value
        ? t(
              unreadCount.value === 1
                  ? 'notifications.unread_one'
                  : 'notifications.unread_many',
              { count: unreadCount.value },
          )
        : t('notifications.notifications'),
);
</script>

<template>
    <Sheet v-model:open="open">
        <SheetTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative"
                :aria-label="label"
            >
                <Bell class="size-5" />
                <span
                    v-if="hasUnread"
                    class="bg-primary text-primary-foreground absolute -end-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-4 font-medium"
                    aria-hidden="true"
                >
                    {{ badge }}
                </span>
            </Button>
        </SheetTrigger>

        <SheetContent side="right" class="flex w-full flex-col sm:max-w-md">
            <SheetHeader>
                <SheetTitle>{{ t('notifications.notifications') }}</SheetTitle>
            </SheetHeader>

            <div class="flex min-h-0 flex-1 flex-col px-4 pb-4">
                <NotificationPanel />
            </div>
        </SheetContent>
    </Sheet>
</template>
