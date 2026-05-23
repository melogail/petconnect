<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import { SendHorizonal } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    conversationId: number;
}>();

const textareaRef = ref<HTMLTextAreaElement | null>(null);

const form = useForm({
    content: '',
});

const autoResize = () => {
    const el = textareaRef.value;

    if (!el) {
        return;
    }

    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 160)}px`;
};

const submitMessage = () => {
    if (!form.content.trim() || !props.conversationId) {
        return;
    }

    form.post(
        route('conversations.messages.store', {
            conversation: props.conversationId,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('content');

                if (textareaRef.value) {
                    textareaRef.value.style.height = 'auto';
                }
            },
        },
    );
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submitMessage();
    }
};
</script>

<template>
    <div class="border-border bg-card/80 border-t px-4 py-3">
        <form class="flex items-end gap-2" @submit.prevent="submitMessage">
            <label class="sr-only" for="message-input">Message</label>

            <div class="relative flex-1">
                <textarea
                    id="message-input"
                    ref="textareaRef"
                    v-model="form.content"
                    rows="1"
                    placeholder="Type a message… (Enter to send, Shift+Enter for new line)"
                    class="border-border bg-background text-foreground placeholder:text-muted-foreground block w-full resize-none rounded-2xl border px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20"
                    @input="autoResize"
                    @keydown="handleKeydown"
                />
                <p
                    v-if="form.errors.content"
                    class="text-destructive mt-1.5 px-1 text-xs"
                >
                    {{ form.errors.content }}
                </p>
            </div>

            <Button
                type="submit"
                size="icon"
                class="mb-0.5 h-10 w-10 shrink-0 rounded-full bg-violet-600 text-white shadow-md transition-all hover:bg-violet-700 hover:shadow-violet-200 disabled:opacity-40 dark:hover:shadow-violet-900/40"
                :disabled="form.processing || !form.content.trim()"
            >
                <SendHorizonal class="h-4 w-4" />
                <span class="sr-only">Send</span>
            </Button>
        </form>
    </div>
</template>
