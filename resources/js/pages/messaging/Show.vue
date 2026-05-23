<script setup lang="ts">
import { useAuthUser } from '@/composables/useAuthUser';
import ConversationHeader from '@/components/messaging/ConversationHeader.vue';
import MessageComposer from '@/components/messaging/MessageComposer.vue';
import MessageThread from '@/components/messaging/MessageThread.vue';
import MainLayout from '@/layouts/MainLayout.vue';
import type {
    MessagingConversation,
    MessagingMessage,
    PaginatedResponse,
} from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const auth = useAuthUser();

const props = defineProps<{
    conversation: MessagingConversation;
    messages: PaginatedResponse<MessagingMessage>;
}>();

const conversationData = computed(() => props.conversation);
const users = computed(() => conversationData.value.users ?? []);
const messageList = computed(() => props.messages.data ?? []);
const conversationId = computed(() => Number(conversationData.value.id ?? 0));

const peer = computed(() => {
    const row = users.value.find((user) => user.id !== auth.value?.id);

    return row ?? { id: 0, name: 'Member', avatar: null };
});

const peerName = computed(() => peer.value.name || 'Conversation');
const peerAvatar = computed(
    () =>
        peer.value.avatar ||
        `https://ui-avatars.com/api/?name=${encodeURIComponent(peerName.value)}`,
);
</script>

<template>
    <Head :title="`Chat with ${peerName}`" />

    <MainLayout>
        <div
            class="mx-auto flex h-[calc(100vh-4rem)] w-full max-w-3xl flex-col px-4 py-4 sm:px-6"
        >
            <ConversationHeader
                :peer-name="peerName"
                :peer-avatar="peerAvatar"
            />

            <div
                class="border-border bg-background flex flex-1 flex-col overflow-hidden rounded-2xl border shadow-sm"
            >
                <MessageThread
                    :messages="messageList"
                    :current-user-id="auth?.id ?? null"
                    :current-user="auth ?? null"
                    :peer="peer"
                />
                <MessageComposer :conversation-id="conversationId" />
            </div>
        </div>
    </MainLayout>
</template>
