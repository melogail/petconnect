<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pin, PinOff, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import MessageEditDialog from '@/components/messaging/MessageEditDialog.vue';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/composables/useLocale';
import { formatTime } from '@/lib/datetime';
import {
    destroy as destroyMessage,
    pin as pinMessage,
} from '@/routes/messages';
import type { Message } from '@/types';

/**
 * One message in a thread.
 *
 * `is_edited` is the `edited_at` column, not a comparison against `updated_at`:
 * pinning writes the row without changing the text, and a derived flag would
 * have called that an edit.
 *
 * `can_edit` closes 15 minutes after sending (`MessagePolicy::update`), so it is
 * true at render time and quietly false later — the control simply disappears
 * on the next render.
 *
 * `can_pin` is `MessagePolicy::pin`, answered per row on the resource because
 * the loader `chaperone()`s the conversation onto every message. It is read,
 * never re-derived here: `.ai/rules/resources.md` records the pin control being
 * decided in Vue from `isVerified() && hasParticipant()` as the exact mistake
 * that flag exists to stop. It is `false` — not `undefined` — when the relation
 * was missing, so a bare truthiness check is safe.
 */
const { message } = defineProps<{ message: Message }>();

const { tag } = useLocale();

const sentAt = computed(() => formatTime(message.created_at, tag.value));
const alignment = computed(() =>
    message.is_mine ? 'items-end self-end' : 'items-start self-start',
);
</script>

<template>
    <div class="group flex max-w-[85%] flex-col gap-1" :class="alignment">
        <div
            class="rounded-2xl px-4 py-2 text-sm leading-relaxed whitespace-pre-wrap"
            :class="
                message.is_mine
                    ? 'bg-primary text-primary-foreground rounded-br-sm'
                    : 'bg-muted rounded-bl-sm'
            "
        >
            {{ message.content }}
        </div>

        <div
            class="text-muted-foreground flex items-center gap-2 px-1 text-xs"
            :class="message.is_mine ? 'flex-row-reverse' : ''"
        >
            <span>{{ sentAt }}</span>
            <span v-if="message.is_edited">edited</span>
            <Pin v-if="message.is_pinned" class="size-3" aria-label="Pinned" />

            <span
                class="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100"
            >
                <MessageEditDialog v-if="message.can_edit" :message="message" />

                <Button
                    v-if="message.can_pin"
                    as-child
                    variant="ghost"
                    size="icon-sm"
                >
                    <Link
                        :href="pinMessage(message.id)"
                        as="button"
                        preserve-scroll
                        preserve-state
                        :aria-label="message.is_pinned ? 'Unpin' : 'Pin'"
                    >
                        <PinOff v-if="message.is_pinned" class="size-3.5" />
                        <Pin v-else class="size-3.5" />
                    </Link>
                </Button>

                <Button
                    v-if="message.can_delete"
                    as-child
                    variant="ghost"
                    size="icon-sm"
                >
                    <Link
                        :href="destroyMessage(message.id)"
                        as="button"
                        preserve-scroll
                        preserve-state
                        aria-label="Delete message"
                    >
                        <Trash2 class="size-3.5" />
                    </Link>
                </Button>
            </span>
        </div>
    </div>
</template>
