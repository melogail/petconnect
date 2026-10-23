<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ConversationList from '@/components/messaging/ConversationList.vue';
import Heading from '@/components/Heading.vue';
import type { Conversation, Paginated } from '@/types';
import { useTranslations } from '@/composables/useTranslations';

const { conversations } = defineProps<{
    conversations: Paginated<Conversation>;
}>();

const { t } = useTranslations();

const unreadCount = computed(
    () =>
        conversations.data.filter((conversation) => conversation.unread).length,
);

const description = computed(() =>
    unreadCount.value > 0
        ? t('messaging.unread_on_page', { count: unreadCount.value })
        : t('messaging.conversations_count', {
              count: conversations.meta.total,
          }),
);
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-8 sm:px-6">
        <Head :title="t('messaging.messages')" />

        <Heading :title="t('messaging.messages')" :description="description" />

        <ConversationList :conversations="conversations" />
    </div>
</template>
