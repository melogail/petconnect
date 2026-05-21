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
        class="divide-y divide-gray-200 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800"
    >
        <InboxListItem v-for="row in inbox" :key="row.conversation.id" :row="row" />
    </ul>
</template>
