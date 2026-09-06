<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatRelative } from '@/lib/datetime';
import { show as showConversation } from '@/routes/conversations';
import type { ConversationPreview } from '@/types';

/**
 * One row of the header's messages menu: who it is with, when, and the first
 * line of what was said.
 *
 * Props in, event out. It reads no shared state — the viewer's id arrives as a
 * prop and the dismissal is the parent's business — so the same row renders
 * identically in the public header and the member header.
 *
 * ## The "You: " prefix is a translated sentence, not a concatenation
 *
 * Legacy built it in `lib/utils.ts` as `` `You: ${trimmed}` ``, an English
 * literal glued to the front of the snippet, which puts the label on the wrong
 * side of the text under `dir="rtl"` and cannot be translated at all.
 * `messaging.you_said` carries the whole line with a `:message` placeholder
 * instead, so Arabic reads `أنت: …` and the ordering is the catalogue's
 * decision rather than JavaScript's.
 *
 * ## The timestamp is formatted here, from an ISO string
 *
 * The legacy `messaging` prop shipped a pre-rendered `time`. This payload ships
 * `last_message_at` as ISO and `formatRelative` renders it in the page's
 * language — the same helper `ConversationListItem` uses on the inbox page, so
 * a thread cannot be "2 hours ago" in the menu and something else one click
 * later. A string rendered on the server would also freeze both the locale and
 * the reading time into text that goes stale while the menu is open.
 *
 * ## Colours: tokens for surfaces, the brand ramp for the accents
 *
 * Legacy hardcoded `bg-violet-50` / `dark:bg-violet-900/20` for the unread row,
 * `ring-violet-400` for the unread avatar and `bg-violet-500` for the dot.
 * `primary-50` … `primary-900` are the same violet ramp registered as tokens
 * (`app.css`, where they are documented as holding the same value in both
 * schemes), so those four keep their exact legacy step. Everything that was a
 * grey — the divider, the hover, the two text colours — becomes a token,
 * because matching legacy's hex does not match legacy's appearance under this
 * app's blue-tinted dark scheme.
 *
 * The avatar ring is `ring-popover`, i.e. the panel it sits on, which is what
 * legacy's `ring-white dark:ring-gray-700` was reaching for by hand.
 *
 * One addition rather than a translation, and it is ours, not legacy's: an
 * unread snippet also takes `text-foreground`, where legacy added only
 * `font-medium`. The reason is that the grey underneath it is not legacy's
 * grey. Legacy's snippet was `text-gray-600` (#4B5563, **7.44:1** on white,
 * computed from the sRGB values); the token that replaces it is
 * `--muted-foreground` (#637083, **5.02:1** on `--background`, which is
 * `#FFFFFF` in light — both figures are against the same white, and the second
 * is app.css's own annotation). Weight alone on the lighter grey does not
 * separate an unread row from a read one, so the colour carries the rest.
 *
 * ## No `prefetch`
 *
 * `ConversationListItem` prefetches, and it is right to: the inbox is the page
 * a reader scans before choosing. Here five rows sit inside a menu that opens
 * on one click, so prefetching them would issue five thread requests for the
 * gesture of glancing at the badge. `conversations.show` is a pure read
 * (.ai/rules/controllers.md), so this is a cost decision, not a safety one.
 */
const { preview, viewerId = null } = defineProps<{
    preview: ConversationPreview;
    /** `auth.user.id`, or null when it could not be read. */
    viewerId?: number | null;
}>();

const emit = defineEmits<{ navigate: [] }>();

const { t } = useTranslations();
const { tag } = useLocale();

const peerName = computed(
    () => preview.peer?.name ?? t('messaging.conversation'),
);

const stamp = computed(() =>
    formatRelative(preview.last_message_at, tag.value),
);

const isMine = computed(
    () => viewerId !== null && preview.last_message_sender_id === viewerId,
);

/**
 * `UserAvatar` types its `class` prop as a plain string — it hands it to `cn()`
 * — so the unread ring is composed here rather than as an object binding, which
 * `vue-tsc` rejects on a typed prop.
 */
const avatarClass = computed(() =>
    preview.unread
        ? 'ring-popover ring-primary-400 size-10 ring-2'
        : 'ring-popover size-10 ring-2',
);

const snippet = computed(() => {
    const message = preview.last_message_snippet?.trim();

    if (!message) {
        return t('messaging.no_messages_yet');
    }

    return isMine.value ? t('messaging.you_said', { message }) : message;
});
</script>

<template>
    <Link
        :href="showConversation(preview.id)"
        class="group border-border hover:bg-accent/50 relative block border-b px-4 py-3 transition-colors duration-150"
        :class="{ 'bg-primary-50 dark:bg-primary-900/20': preview.unread }"
        @click="emit('navigate')"
    >
        <div class="flex items-start gap-3">
            <div class="relative shrink-0">
                <UserAvatar
                    :name="peerName"
                    :avatar="preview.peer?.avatar ?? null"
                    :class="avatarClass"
                />
                <span
                    v-if="preview.unread"
                    class="bg-primary-500 border-popover absolute -end-1 -top-1 size-3 rounded-full border-2"
                    aria-hidden="true"
                />
            </div>

            <div class="min-w-0 flex-1">
                <div class="mb-0.5 flex items-center justify-between">
                    <p
                        class="text-foreground truncate text-sm font-medium"
                        :class="{ 'font-semibold': preview.unread }"
                    >
                        {{ peerName }}
                    </p>
                    <time
                        v-if="preview.last_message_at"
                        :datetime="preview.last_message_at"
                        class="text-muted-foreground ms-2 text-xs whitespace-nowrap"
                    >
                        {{ stamp }}
                    </time>
                </div>
                <p
                    class="text-muted-foreground truncate text-sm"
                    :class="{ 'text-foreground font-medium': preview.unread }"
                >
                    {{ snippet }}
                </p>
            </div>
        </div>
    </Link>
</template>
