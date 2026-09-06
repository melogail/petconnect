<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { useLocale } from '@/composables/useLocale';
import { formatRelative } from '@/lib/datetime';
import { show as showConversation } from '@/routes/conversations';
import type { Conversation } from '@/types';

/**
 * One inbox row.
 *
 * `unread` is only meaningful here: the inbox eager loads participants *and*
 * `lastMessage`, which is what `ConversationResource` needs to answer the
 * question. The thread page loads participants only and always reports `false`.
 */
const { conversation } = defineProps<{ conversation: Conversation }>();

const { tag } = useLocale();

const peer = computed(() => conversation.peer ?? null);
const preview = computed(() => {
    const message = conversation.last_message;

    if (!message) {
        return 'No messages yet.';
    }

    return message.is_mine ? `You: ${message.content}` : message.content;
});
const stamp = computed(() =>
    formatRelative(conversation.last_message_at, tag.value),
);
</script>

<template>
    <Link
        :href="showConversation(conversation.id)"
        class="hover:bg-accent/50 flex items-center gap-3 rounded-lg px-3 py-3 transition-colors"
        prefetch
    >
        <UserAvatar
            :name="peer?.name ?? 'Conversation'"
            :avatar="peer?.avatar ?? null"
            class="size-11 shrink-0"
        />

        <div class="min-w-0 flex-1">
            <div class="flex items-baseline justify-between gap-2">
                <p class="truncate font-medium">
                    {{ peer?.name ?? 'Conversation' }}
                </p>
                <span class="text-muted-foreground shrink-0 text-xs">
                    {{ stamp }}
                </span>
            </div>
            <p
                class="truncate text-sm"
                :class="
                    conversation.unread
                        ? 'text-foreground font-medium'
                        : 'text-muted-foreground'
                "
            >
                {{ preview }}
            </p>
        </div>

        <span
            v-if="conversation.unread"
            class="bg-primary size-2.5 shrink-0 rounded-full"
            aria-label="Unread"
        />
    </Link>
</template>
