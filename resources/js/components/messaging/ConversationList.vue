<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import ConversationListItem from '@/components/messaging/ConversationListItem.vue';
import Pagination from '@/components/Pagination.vue';
import type { Conversation, Paginated } from '@/types';

defineProps<{ conversations: Paginated<Conversation> }>();
</script>

<template>
    <div class="space-y-4">
        <EmptyState
            v-if="conversations.data.length === 0"
            :icon="Inbox"
            title="No conversations yet"
            description="Open a thread from any member's profile or listing."
        />

        <template v-else>
            <ul class="divide-border divide-y">
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
