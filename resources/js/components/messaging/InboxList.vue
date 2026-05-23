<script setup lang="ts">
import InboxEmptyState from '@/components/messaging/InboxEmptyState.vue';
import InboxListItem from '@/components/messaging/InboxListItem.vue';
import type { MessagingInboxRow } from '@/types';

defineProps<{
    inbox: MessagingInboxRow[];
}>();
</script>

<template>
    <InboxEmptyState v-if="inbox.length === 0" />

    <ul
        v-else
        class="border-border bg-card divide-border divide-y overflow-hidden rounded-2xl border shadow-sm"
    >
        <InboxListItem
            v-for="row in inbox"
            :key="row.conversation.id"
            :row="row"
        />
    </ul>
</template>
