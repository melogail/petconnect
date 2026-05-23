<script setup lang="ts">
import { useAuthUser } from '@/composables/useAuthUser';
import {
    fallbackAvatar,
    formatConversationTimestamp,
    messagePreview,
} from '@/lib/utils';
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
const peerAvatar = props.row.peer?.avatar || fallbackAvatar(peerName);
const previewText = computed(() =>
    messagePreview(
        props.row.last_message?.content ?? '',
        props.row.last_message?.sender_id === currentUserId.value,
    ),
);
const messageTimestamp = formatConversationTimestamp(
    props.row.conversation.last_message_at,
);
</script>

<template>
    <li>
        <Link
            :href="route('conversations.show', row.conversation.id)"
            class="hover:bg-muted/50 group flex items-center gap-3.5 px-4 py-3.5 transition-colors"
            :class="{
                'bg-violet-50/50 dark:bg-violet-950/20': row.unread,
            }"
        >
            <!-- Avatar with unread indicator -->
            <div class="relative shrink-0">
                <img
                    :src="peerAvatar"
                    :alt="peerName"
                    class="h-12 w-12 rounded-full object-cover ring-2 transition-all"
                    :class="
                        row.unread
                            ? 'ring-violet-400 dark:ring-violet-500'
                            : 'ring-border'
                    "
                />
                <span
                    v-if="row.unread"
                    class="border-background absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 bg-violet-500"
                />
            </div>

            <!-- Content -->
            <div class="min-w-0 flex-1">
                <div class="flex items-baseline justify-between gap-2">
                    <span
                        class="truncate text-sm font-semibold transition-colors"
                        :class="
                            row.unread
                                ? 'text-violet-700 dark:text-violet-300'
                                : 'text-foreground group-hover:text-violet-700 dark:group-hover:text-violet-300'
                        "
                    >
                        {{ peerName }}
                    </span>
                    <span
                        v-if="messageTimestamp"
                        class="shrink-0 text-[11px]"
                        :class="
                            row.unread
                                ? 'font-semibold text-violet-500'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ messageTimestamp }}
                    </span>
                </div>
                <p
                    class="mt-0.5 truncate text-sm"
                    :class="
                        row.unread
                            ? 'text-foreground font-medium'
                            : 'text-muted-foreground'
                    "
                >
                    {{ previewText }}
                </p>
            </div>
        </Link>
    </li>
</template>
