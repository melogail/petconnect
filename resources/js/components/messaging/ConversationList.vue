<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import ConversationListItem from '@/components/messaging/ConversationListItem.vue';
import Pagination from '@/components/Pagination.vue';
import type { Conversation, Paginated } from '@/types';
import { useTranslations } from '@/composables/useTranslations';

defineProps<{ conversations: Paginated<Conversation> }>();

const { t } = useTranslations();
</script>

<template>
    <div class="space-y-4">
        <EmptyState
            v-if="conversations.data.length === 0"
            :icon="Inbox"
            :title="t('messaging.no_conversations_yet')"
            :description="t('messaging.no_conversations_hint')"
        />

        <template v-else>
            <ul
                class="divide-border bg-card border-border shadow-card divide-y overflow-hidden rounded-[20px] border"
            >
                <li
                    v-for="conversation in conversations.data"
                    :key="conversation.id"
                >
                    <ConversationListItem :conversation="conversation" />
                </li>
            </ul>

            <Pagination :links="conversations.meta.links" />
        </template>
    </div>
</template>
