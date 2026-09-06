<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { useId } from 'vue';
import NotificationRow from '@/components/notifications/NotificationRow.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
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
 * This is the one place the phase 5 sweep did **not** follow legacy.
 * `NotificationsSheet.vue:51-71` made the whole row a single `<button>` that
 * posted `notifications.read` and then `router.visit()`ed the url when there
 * was one. It clears every row, this one included — but it spends a `<button>`
 * on what is a navigation, so the destination is not a link: no middle-click,
 * no open-in-new-tab, no copy-link, nothing in the status bar, and a keyboard
 * user who wants to clear a row has no choice but to open it. That is a legacy
 * defect, and defects are not reproduced. The row's *appearance* is legacy's;
 * its element structure is not, deliberately.
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
 * ## Following the link also asks the sheet to close
 *
 * `navigate` is emitted from the `<Link>` and from nowhere else. The header is
 * mounted by the layout resolver and survives a same-layout visit, so nothing
 * remounts the bell or resets its `open` — without this the reader arrives at
 * the notification's destination still behind a modal overlay. The event goes
 * up through `NotificationPanel` rather than the sheet being closed here,
 * because `open` is the bell's state and this component has no business
 * reaching into it. `MessagesDropdown` closes on row activation for the same
 * reason.
 *
 * The mark-read button deliberately does not emit it: clearing a row is a thing
 * you do *to* the inbox, and the sheet closing under that gesture is exactly
 * the `DropdownMenu` behaviour `NotificationBell` chose a sheet to avoid.
 *
 * ## The padding and the tint are legacy's
 *
 * `px-6 py-4` rows separated by `divide-y` (applied by `NotificationPanel` on
 * the `<ul>`), with an unread row tinted `primary-50/70`. The mark-read button
 * is `me-6`, not `me-4`, so that an unread row and a read one end on the same
 * edge — the button replaces the row's own end padding rather than sitting
 * inside a narrower one. Legacy's dark tint
 * was `violet-950/20`; `app.css` registers the ramp to `primary-900` and no
 * further, so the dark step here is `primary-900/20` — one stop lighter than
 * legacy's and the closest the token ramp reaches.
 *
 * The button's label used to be an English literal, because `lang/*.json` had
 * `notifications.mark_all_as_read` and no singular, and borrowing the plural
 * key would have shipped a label that says the wrong thing in two languages
 * instead of one. Phase 5 added `notifications.mark_as_read` to both
 * catalogues, so it is a `t()` call now.
 *
 * ## The button names itself with the row it clears
 *
 * `notifications.mark_as_read` alone is the same string on every unread row, so
 * an elements list — the way a screen-reader user moves between controls
 * *without* their surroundings — showed N buttons called "Mark as read" and
 * nothing to choose between them. The name is built with `aria-labelledby`
 * instead, naming the button's own hidden label and then `NotificationRow`'s
 * sentence paragraph, which an accessible-name calculation concatenates: "Mark
 * as read :sentence", distinct per row.
 *
 * Three deliberate choices in that, each of which has an obvious-looking
 * alternative:
 *
 * - `aria-labelledby`, not an `aria-label` interpolating the sentence. The
 *   sentence is `NotificationRow`'s `t(message_key, message_replace)`; a second
 *   copy of that computation here is a second thing to keep in step with the
 *   payload and the catalogue. A reference to the rendered element cannot
 *   drift from it.
 * - Not `aria-describedby`. A description is not the name, and the elements
 *   list this fixes shows names — the buttons would still all read alike there.
 * - An `sr-only` span rather than an `aria-label` for the button's own half:
 *   `aria-label` overrides an element's contents (`MessagesDropdown` records
 *   the same trap), while `aria-labelledby` announces exactly the elements it
 *   names, so the span is what gets read.
 *
 * The ids come from `useId()` rather than from `notification.id`, because they
 * have to be unique in the document and nothing here guarantees one inbox is
 * mounted at a time.
 *
 * Residual, and left alone deliberately: two rows whose sentence is word for
 * word the same still collide — a like on one pet from two accounts that have
 * both since been deleted renders "Someone liked your pet Rex" twice.
 * `NotificationRow`'s `<time>` would break the tie, at the cost of a timestamp
 * on the end of every one of these names, and "2 hours ago" is itself not
 * unique. The subject is what tells the rows apart in practice.
 */
const { notification } = defineProps<{ notification: InboxNotification }>();

const emit = defineEmits<{
    read: [notification: InboxNotification];
    navigate: [];
}>();

const { t } = useTranslations();

const messageId = useId();
const markReadLabelId = useId();

/** Following the deep link: clear the row, and let the sheet close behind it. */
function open(): void {
    emit('read', notification);
    emit('navigate');
}

const ROW_CLASS =
    'hover:bg-muted/50 focus-visible:ring-ring flex-1 px-6 py-4 transition-colors focus-visible:ring-2 focus-visible:outline-hidden';
</script>

<template>
    <li
        class="flex items-start"
        :class="{
            'bg-primary-50/70 dark:bg-primary-900/20': !notification.read,
        }"
    >
        <Link
            v-if="notification.url"
            :href="notification.url"
            :class="ROW_CLASS"
            @click="open()"
        >
            <NotificationRow
                :notification="notification"
                :message-id="messageId"
            />
        </Link>
        <div v-else :class="ROW_CLASS">
            <NotificationRow
                :notification="notification"
                :message-id="messageId"
            />
        </div>

        <Button
            v-if="!notification.read"
            variant="ghost"
            size="icon"
            class="me-6 mt-3 shrink-0"
            :aria-labelledby="`${markReadLabelId} ${messageId}`"
            @click="emit('read', notification)"
        >
            <Check class="size-4" aria-hidden="true" />
            <span :id="markReadLabelId" class="sr-only">
                {{ t('notifications.mark_as_read') }}
            </span>
        </Button>
    </li>
</template>
