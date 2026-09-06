<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import ConversationHeader from '@/components/messaging/ConversationHeader.vue';
import MessageComposer from '@/components/messaging/MessageComposer.vue';
import MessageThread from '@/components/messaging/MessageThread.vue';
import { useMessagingPreviews } from '@/composables/useMessagingPreviews';
import { index as conversationsIndex, read } from '@/routes/conversations';
import { index as messagesIndex } from '@/routes/conversations/messages';
import type { Conversation, Message, MessageBounds, Paginated } from '@/types';

/**
 * One thread.
 *
 * Two things about the payload shape drive this page:
 *
 * - **The thread pages newest first** (`created_at DESC`, `id DESC`), because
 *   the first page of a conversation is its end, which is the part a reader
 *   wants. Display order is the reverse, and older pages are pulled backwards
 *   from `conversations.messages.index`, which answers plain JSON rather than a
 *   page object — so it goes through `useHttp`, not through a visit.
 * - **`can_send` gates the composer.** `MessagePolicy::create` is answered per
 *   row on `ConversationResource` and costs no query, so "this person is not
 *   accepting messages" is visible before anything is typed rather than as a
 *   403 after it.
 * - **`conversations.show` is a pure read.** Marking the thread read is a
 *   separate POST fired after render, precisely so that prefetching or an
 *   instant visit cannot clear somebody's unread badge by hovering the inbox.
 */
const { conversation, messages } = defineProps<{
    conversation: Conversation;
    messages: Paginated<Message>;
    /**
     * `petconnect.messaging.max_length`, built from the same accessor the
     * `max:` rule is built from. The composer reads it instead of a literal.
     */
    messageBounds: MessageBounds;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Messages', href: conversationsIndex() }],
    },
});

/** Older pages, newest-first as the endpoint returns them. */
const olderPages = ref<Message[]>([]);
const loadedPage = ref(1);
const loadingOlder = ref(false);

const older = useHttp<Record<string, never>, Paginated<Message>>();

const hasOlder = computed(() => loadedPage.value < messages.meta.last_page);

/**
 * Newest-first pages merged, deduplicated and flipped into display order.
 *
 * Deduplication matters because sending a message shifts every page boundary by
 * one: the page 2 already in hand overlaps the refreshed page 1 by a row.
 */
const thread = computed<Message[]>(() => {
    const byId = new Map<number, Message>();

    for (const message of [...messages.data, ...olderPages.value]) {
        byId.set(message.id, message);
    }

    return [...byId.values()].sort(
        (a, b) => a.created_at.localeCompare(b.created_at) || a.id - b.id,
    );
});

async function loadOlder(): Promise<void> {
    if (loadingOlder.value || !hasOlder.value) {
        return;
    }

    loadingOlder.value = true;

    try {
        const page = await older.get(
            messagesIndex.url(conversation.id, {
                query: { page: loadedPage.value + 1 },
            }),
        );

        olderPages.value = [...olderPages.value, ...page.data];
        loadedPage.value = page.meta.current_page;
    } finally {
        loadingOlder.value = false;
    }
}

/**
 * Opening a thread marks it read, and the header badge has to be told.
 *
 * The server write below is the authority and is unchanged. What it cannot
 * reach is `useMessagingPreviews`' **module-level** state — the previews array
 * and `unreadCount` live outside any component so both headers share one copy,
 * and no page prop feeds them. The messages dropdown already called this on the
 * row it navigated from; every other way in did not, so the badge went on
 * counting a conversation the reader was looking at.
 *
 * `markConversationRead` is a local mutation and issues no request — it
 * decrements the count and flips that preview's `unread` flag, and returns
 * early when the id is not in the cached list or is already read. So this adds
 * no network cost to the visit.
 *
 * ## What this fixes, and what it does not — measured, 2026-09-03
 *
 * Opening conversation 3 (unread) **from the inbox**, i.e. a client-side
 * Inertia visit off `/conversations`: the header trigger goes from
 * `Messages (1 unread)` to `Messages`, and the only requests on the visit are
 * `GET /conversations/3`, `POST /conversations/3/read` and this page's own
 * `only: ['conversation']` reload. No `GET /conversations/previews` at all —
 * the module cache short-circuits it, which is exactly why the badge could go
 * stale and stay stale.
 *
 * Opening the same thread by **pasting the URL**, i.e. a cold document load,
 * this call does nothing: the module state starts empty and the header's own
 * `GET /conversations/previews` is still in flight when `onMounted` runs, so
 * `find()` misses and the early return fires. Measured on `/conversations/1`:
 * the request order was `GET /conversations/1`, `GET /conversations/previews`,
 * `POST /conversations/1/read`, and the badge stayed at `2 unread` because the
 * previews response was computed before the read was written. That is a race
 * inside `useMessagingPreviews` — the mark has to be replayed once the fetch
 * lands — and closing it belongs in that composable, not here.
 */
const { markConversationRead } = useMessagingPreviews();

onMounted(() => {
    router.post(
        read.url(conversation.id),
        {},
        { preserveScroll: true, preserveState: true, only: ['conversation'] },
    );

    markConversationRead(conversation.id);
});
</script>

<template>
    <div
        class="mx-auto flex h-[calc(100vh-6rem)] w-full max-w-3xl flex-col p-4"
    >
        <Head :title="conversation.peer?.name ?? 'Conversation'" />

        <div
            class="border-border bg-background flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border"
        >
            <ConversationHeader :conversation="conversation" />

            <MessageThread
                :messages="thread"
                :has-older="hasOlder"
                :loading-older="loadingOlder"
                @load-older="loadOlder"
            />

            <MessageComposer
                :conversation-id="conversation.id"
                :max-length="messageBounds.max_length"
                :can-send="conversation.can_send"
            />
        </div>
    </div>
</template>
