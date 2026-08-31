<script setup lang="ts">
import { MessageCircle } from '@lucide/vue';
import { computed, onBeforeUpdate, onMounted, onUpdated, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import MessageBubble from '@/components/messaging/MessageBubble.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useLocale } from '@/composables/useLocale';
import { formatDayHeading, toDayKey } from '@/lib/datetime';
import type { Message } from '@/types';

/**
 * The scrollable thread, oldest at the top.
 *
 * It is handed messages already in display order — the backend pages newest
 * first, so the page that owns the data is the one that reverses it — and asks
 * for the next older page through `load-older`.
 *
 * ## Why this component owns the scroll position
 *
 * It owns the only scrolling element, so nothing above it can put the reader
 * anywhere. Oldest-at-top plus a browser's default scroll of zero opened every
 * conversation thirty messages back, while `conversations.read` fired on mount
 * regardless — clearing the badge for a message that was never on screen.
 *
 * The ids at the two ends are what tell the three cases apart, and they are
 * enough: a message is never inserted into the middle of a thread.
 *
 * - **Mount** and **a new newest message** (a send, or a reload that brings
 *   one in) go to the bottom, which is where the conversation is.
 * - **A new oldest message** is "Load older messages" prepending a page. The
 *   viewport is pinned to the same pixel of content by adding however much the
 *   document grew, so the line being read does not jump off screen.
 * - Anything else — an edit, a pin — leaves the scroll position alone.
 *
 * ## Two assumptions the three cases rest on, neither of them checked here
 *
 * **An empty thread's first message takes the `prepended` branch.** With no
 * messages, `oldestId` and `newestId` are both null; the first send changes
 * *both*, and `prepended` is tested first, so the arithmetic branch runs for
 * what is really an append. It lands at the bottom anyway, but by accident:
 * `heightBeforeUpdate` and `topBeforeUpdate` are the geometry of an empty
 * viewport, so the target overshoots and the browser clamps `scrollTop` to the
 * maximum — which is the bottom. Correct output, wrong reason.
 *
 * **A wholesale replacement of `messages` would take `prepended` too.** Both
 * ends change, `prepended` wins the test, and the viewport is pinned to a pixel
 * offset that meant something in the *previous* conversation. That cannot
 * happen today: `messaging/Show` is only ever reached by a fresh visit, so the
 * component remounts and `onMounted` scrolls to the bottom. It becomes reachable
 * the day an inbox pane sits beside the thread and switching conversations
 * swaps the prop on a live instance — at which point the reader opens their
 * next conversation somewhere in the middle of its history.
 *
 * Both want the same fix when one of them matters: decide the case from what
 * changed at *each* end rather than testing `prepended` first — an append is
 * "the newest moved and the oldest did not", and neither-matches is a
 * replacement that belongs at the bottom.
 */
const { messages, hasOlder, loadingOlder } = defineProps<{
    messages: Message[];
    hasOlder: boolean;
    loadingOlder: boolean;
}>();

const emit = defineEmits<{ 'load-older': [] }>();

const { tag } = useLocale();

const viewport = ref<HTMLElement | null>(null);

const oldestId = computed(() => messages[0]?.id ?? null);
const newestId = computed(() => messages[messages.length - 1]?.id ?? null);

/** The ends as they were at the last render, and the geometry that went with it. */
let rendered = { oldest: oldestId.value, newest: newestId.value };
let heightBeforeUpdate = 0;
let topBeforeUpdate = 0;

function scrollToBottom(): void {
    if (viewport.value !== null) {
        viewport.value.scrollTop = viewport.value.scrollHeight;
    }
}

onMounted(scrollToBottom);

onBeforeUpdate(() => {
    heightBeforeUpdate = viewport.value?.scrollHeight ?? 0;
    topBeforeUpdate = viewport.value?.scrollTop ?? 0;
});

onUpdated(() => {
    const element = viewport.value;
    const prepended = oldestId.value !== rendered.oldest;
    const appended = newestId.value !== rendered.newest;

    rendered = { oldest: oldestId.value, newest: newestId.value };

    if (element === null) {
        return;
    }

    if (prepended) {
        element.scrollTop =
            element.scrollHeight - heightBeforeUpdate + topBeforeUpdate;
    } else if (appended) {
        scrollToBottom();
    }
});

/** A day heading is emitted whenever the calendar day changes down the list. */
const rows = computed(() =>
    messages.map((message, index) => ({
        message,
        heading:
            index === 0 ||
            toDayKey(message.created_at) !==
                toDayKey(messages[index - 1].created_at)
                ? formatDayHeading(message.created_at, tag.value)
                : null,
    })),
);
</script>

<template>
    <div ref="viewport" class="flex-1 space-y-3 overflow-y-auto p-4">
        <div v-if="hasOlder" class="flex justify-center">
            <Button
                variant="ghost"
                size="sm"
                :disabled="loadingOlder"
                @click="emit('load-older')"
            >
                <Spinner v-if="loadingOlder" />
                Load older messages
            </Button>
        </div>

        <EmptyState
            v-if="messages.length === 0"
            :icon="MessageCircle"
            title="No messages yet"
            description="Say something to get this started."
        />

        <div v-else class="flex flex-col gap-3">
            <template v-for="row in rows" :key="row.message.id">
                <p
                    v-if="row.heading"
                    class="text-muted-foreground self-center text-xs"
                >
                    {{ row.heading }}
                </p>
                <MessageBubble :message="row.message" />
            </template>
        </div>
    </div>
</template>
