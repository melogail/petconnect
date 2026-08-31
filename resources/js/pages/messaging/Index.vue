<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ConversationList from '@/components/messaging/ConversationList.vue';
import Heading from '@/components/Heading.vue';
import { index as conversationsIndex } from '@/routes/conversations';
import type { Conversation, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Messages', href: conversationsIndex() }],
    },
});

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
    <div class="mx-auto w-full max-w-3xl space-y-6 p-4 sm:p-6">
        <Head title="Messages" />

        <Heading title="Messages" :description="description" />

        <ConversationList :conversations="conversations" />
    </div>
</template>
