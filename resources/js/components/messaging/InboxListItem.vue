<script setup lang="ts">
import { useAuthUser } from '@/composables/useAuthUser';
import { fallbackAvatar, formatConversationTimestamp, messagePreview } from '@/lib/utils';
import type { MessagingInboxRow } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    row: MessagingInboxRow;
}>();

const currentUser = useAuthUser();
const currentUserId = computed(() => currentUser.value?.id ?? null);
const peerName = props.row.peer?.name ?? 'User';
const peerAvatar = props.row.peer?.avatar ?? fallbackAvatar(peerName);
const previewText = computed(() => messagePreview(
    props.row.last_message?.content ?? '',
    props.row.last_message?.sender_id === currentUserId.value,
));
const messageTimestamp = formatConversationTimestamp(props.row.conversation.last_message_at);
</script>

<template>
    <li>
        <Link
            :href="route('conversations.show', row.conversation.id)"
            class="flex gap-4 px-4 py-4 transition-colors hover:bg-violet-50/80 dark:hover:bg-violet-950/30"
            :class="{
                'bg-violet-50/50 dark:bg-violet-950/20': row.unread,
            }"
        >
            <img
                :src="peerAvatar"
                :alt="peerName"
                class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-white dark:ring-gray-800"
            />
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <span
                        class="truncate font-semibold text-gray-900 dark:text-gray-100"
                        :class="{ 'text-violet-700 dark:text-violet-300': row.unread }"
                    >
                        {{ peerName }}
                    </span>
                    <span
                        v-if="messageTimestamp"
                        class="shrink-0 text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ messageTimestamp }}
                    </span>
                </div>
                <p
                    class="mt-0.5 truncate text-sm text-gray-600 dark:text-gray-300"
                    :class="{ 'font-medium text-gray-900 dark:text-gray-100': row.unread }"
                >
                    {{ previewText }}
                </p>
            </div>
            <span
                v-if="row.unread"
                class="mt-1 shrink-0 rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-semibold text-violet-700 dark:bg-violet-900/60 dark:text-violet-200"
            >
                New
            </span>
        </Link>
    </li>
</template>
