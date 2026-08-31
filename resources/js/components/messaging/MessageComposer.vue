<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store as storeMessage } from '@/routes/conversations/messages';

/**
 * Write a message.
 *
 * `preserveState` keeps the thread's own state — the older pages it has already
 * pulled in — across the redirect the store action answers with; without it the
 * page component is rebuilt and the reader is thrown back to the newest page.
 *
 * `messages.store` answers **403** when the recipient is not accepting
 * messages. `ConversationResource::can_send` is `MessagePolicy::create` for
 * this thread, answered per row and free of a query, so the refusal is visible
 * before anything is typed instead of landing after it. The policy still has
 * the last word — the flag is accurate at render time, not afterwards.
 *
 * `maxLength` comes from the page's `messageBounds` prop, which is built from
 * the same accessor the `max:` rule is, so the counter cannot drift from the
 * validator.
 */
const { conversationId, maxLength, canSend } = defineProps<{
    conversationId: number;
    maxLength: number;
    canSend: boolean;
}>();
</script>

<template>
    <p
        v-if="!canSend"
        class="text-muted-foreground border-border border-t p-4 text-sm"
    >
        You cannot send messages in this conversation.
    </p>

    <Form
        v-else
        v-bind="storeMessage.form(conversationId)"
        reset-on-success
        :options="{ preserveScroll: true, preserveState: true }"
        class="border-border flex items-end gap-2 border-t p-3"
        v-slot="{ errors, processing }"
    >
        <div class="flex-1 space-y-1">
            <Textarea
                name="content"
                rows="1"
                required
                :maxlength="maxLength"
                placeholder="Write a message…"
                aria-label="Message"
                class="max-h-40 min-h-10 resize-none"
            />
            <InputError :message="errors.content" />
        </div>

        <Button
            type="submit"
            size="icon"
            :disabled="processing"
            aria-label="Send"
        >
            <Spinner v-if="processing" />
            <Send v-else class="size-4" />
        </Button>
    </Form>
</template>
