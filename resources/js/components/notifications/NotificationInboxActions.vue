<script setup lang="ts">
import { CheckCheck, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useNotificationInbox } from '@/composables/useNotificationInbox';
import { useTranslations } from '@/composables/useTranslations';

/**
 * "Mark all as read" and "Delete all", the two things a reader can do to the
 * whole inbox at once.
 *
 * They were inside `NotificationPanel` until the phase 5 parity sweep. Legacy's
 * `NotificationsSheet` put them in the sheet **header**, above the rule that
 * divides the header from the list, so they read as controls over the inbox
 * rather than as the first item in it — and they stay put when the list
 * scrolls. Moving them there meant lifting them out of the panel, which is why
 * this is its own component rather than a block of markup in `NotificationBell`.
 *
 * `variant="outline"` with `gap-1.5` is legacy's spelling too. They were
 * `ghost` here, which on a `bg-background` sheet header left two labels with no
 * visible affordance at all next to a title of the same weight.
 *
 * It reads `useNotificationInbox` directly rather than taking props: the
 * composable is module-scoped, so there is one inbox and one place its
 * disabled-ness is decided, and threading two booleans and two callbacks
 * through the bell would be a second copy of that decision.
 */
const { notifications, hasUnread, markAllAsRead, deleteAll } =
    useNotificationInbox();

const { t } = useTranslations();
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Button
            type="button"
            variant="outline"
            size="sm"
            class="gap-1.5"
            :disabled="!hasUnread"
            @click="markAllAsRead()"
        >
            <CheckCheck class="size-4" />
            {{ t('notifications.mark_all_as_read') }}
        </Button>

        <Button
            type="button"
            variant="outline"
            size="sm"
            class="text-destructive hover:text-destructive gap-1.5"
            :disabled="notifications.length === 0"
            @click="deleteAll()"
        >
            <Trash2 class="size-4" />
            {{ t('notifications.delete_all') }}
        </Button>
    </div>
</template>
