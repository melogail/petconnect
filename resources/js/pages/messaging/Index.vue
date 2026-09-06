<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ConversationList from '@/components/messaging/ConversationList.vue';
import Heading from '@/components/Heading.vue';
import type { Conversation, Paginated } from '@/types';

const { conversations } = defineProps<{
    conversations: Paginated<Conversation>;
}>();

const unreadCount = computed(
    () =>
        conversations.data.filter((conversation) => conversation.unread).length,
);

const description = computed(() =>
    unreadCount.value > 0
        ? `${unreadCount.value} unread on this page`
        : `${conversations.meta.total} conversations`,
);
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-8 sm:px-6">
        <Head title="Messages" />

        <Heading title="Messages" :description="description" />

        <ConversationList :conversations="conversations" />
    </div>
</template>
