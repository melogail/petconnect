<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import NotificationRow from '@/components/notifications/NotificationRow.vue';
import { Button } from '@/components/ui/button';
import type { InboxNotification } from '@/types';

/**
 * One notification in the list: the row, wherever it points, and the control
 * that clears it.
 *
 * ## Why the "mark read" button is a sibling of the link, not inside it
 *
 * A row with a `url` is a `<Link>`, and an interactive control nested inside an
 * anchor is invalid HTML and unreachable for a keyboard: the anchor swallows
 * Enter. The two sit side by side in a flex row instead, so the reader can tab
 * to "open" and then to "mark read".
 *
 * ## Not every row has somewhere to go
 *
 * `url` is `Route::has()`-guarded on the backend and is null whenever the
 * subject has no page — a like on something that is neither a pet nor a
 * profile, for instance. Those rows are still notifications and still need to
 * be markable, which is why the button is not conditional on the link.
 *
 * Opening a row also marks it read, which is what the reader means by opening
 * it. The mark is optimistic and the request does not block the navigation —
 * see `useNotificationInbox`.
 *
 * The button's label is an English literal rather than a `t()` call because
 * there is no key for it: `lang/*.json` has `notifications.mark_all_as_read`
 * and no singular. `notifications.mark_as_read` is on the list of keys this
 * phase asked for and `lang/` is not the frontend's to write; using the plural
 * key here would have shipped a label that says the wrong thing in two
 * languages instead of one.
 */
const { notification } = defineProps<{ notification: InboxNotification }>();

const emit = defineEmits<{ read: [notification: InboxNotification] }>();

const ROW_CLASS =
    'hover:bg-accent/50 flex-1 rounded-lg p-2 transition-colors focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-hidden';
</script>

<template>
    <li class="flex items-start gap-1">
        <Link
            v-if="notification.url"
            :href="notification.url"
            :class="ROW_CLASS"
            @click="emit('read', notification)"
        >
            <NotificationRow :notification="notification" />
        </Link>
        <div v-else :class="ROW_CLASS">
            <NotificationRow :notification="notification" />
        </div>

        <Button
            v-if="!notification.read"
            variant="ghost"
            size="icon"
            class="mt-2 shrink-0"
            aria-label="Mark as read"
            @click="emit('read', notification)"
        >
            <Check class="size-4" />
        </Button>
    </li>
</template>
