<script setup lang="ts">
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDateTime } from '@/lib/datetime';
import type { TicketMessage } from '@/types';

/**
 * One message of a ticket's thread, in the style guide's chat bubbles
 * (`messaging/MessageBubble`): the member's own messages solid brand on the
 * inline end, the team's on the card surface at the inline start. The byline
 * is the author's name, falling back to "You" / "Support team" when the
 * server has none (a deleted account).
 */
const { message } = defineProps<{ message: TicketMessage }>();

const { t } = useTranslations();
const { tag } = useLocale();

const sentAt = computed(() => formatDateTime(message.created_at, tag.value));

const author = computed(
    () =>
        message.author_name ??
        t(message.is_from_support ? 'support.support_team' : 'support.you'),
);
</script>

<template>
    <li
        class="flex max-w-[85%] flex-col gap-1"
        :class="message.is_from_support ? 'self-start' : 'self-end'"
    >
        <div
            class="rounded-[18px] px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap"
            :class="
                message.is_from_support
                    ? 'bg-card border-border rounded-es-[6px] border'
                    : 'bg-primary text-primary-foreground rounded-ee-[6px]'
            "
        >
            {{ message.body }}
        </div>
        <p
            class="text-muted-foreground flex items-center gap-2 px-1 text-xs"
            :class="message.is_from_support ? '' : 'flex-row-reverse'"
        >
            <span class="font-medium">{{ author }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ sentAt }}</span>
        </p>
    </li>
</template>
