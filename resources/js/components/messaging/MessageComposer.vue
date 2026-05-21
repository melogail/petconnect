<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { Send } from 'lucide-vue-next';

const props = defineProps<{
    conversationId: number;
}>();

const form = useForm({
    content: '',
});

const submitMessage = () => {
    if (!form.content.trim() || !props.conversationId) {
        return;
    }

    form.post(
        route('conversations.messages.store', { conversation: props.conversationId }),
        {
            preserveScroll: true,
            onSuccess: () => form.reset('content'),
        },
    );
};
</script>

<template>
    <form
        class="border-t border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900"
        @submit.prevent="submitMessage"
    >
        <label class="sr-only" for="message-input">Message</label>
        <div class="flex gap-2">
            <textarea
                id="message-input"
                v-model="form.content"
                rows="2"
                placeholder="Type a message…"
                class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex-1 resize-none rounded-xl border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2"
            />
            <Button
                type="submit"
                class="self-end rounded-xl bg-violet-600 px-4 hover:bg-violet-700"
                :disabled="form.processing || !form.content.trim()"
            >
                <Send class="mr-2 h-4 w-4" />
                Send
            </Button>
        </div>
        <p v-if="form.errors.content" class="mt-2 text-sm text-red-600">
            {{ form.errors.content }}
        </p>
    </form>
</template>
