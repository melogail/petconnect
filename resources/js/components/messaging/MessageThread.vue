<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { nextTick, ref, watch } from 'vue';
import { MoreVertical, Pencil, Trash2 } from 'lucide-vue-next';
import type { MessagingMessage } from '@/types';

const props = defineProps<{
    messages: MessagingMessage[];
    currentUserId: number | null;
}>();

const editingId = ref<number | null>(null);
const editContent = ref('');
const containerRef = ref<HTMLElement | null>(null);

const startEdit = (message: MessagingMessage) => {
    editingId.value = message.id;
    editContent.value = message.content;
};

const cancelEdit = () => {
    editingId.value = null;
    editContent.value = '';
};

const saveEdit = (messageId: number) => {
    router.put(
        route('messages.update', messageId),
        { content: editContent.value },
        {
            preserveScroll: true,
            onFinish: () => cancelEdit(),
        },
    );
};

const destroyMessage = (messageId: number) => {
    if (!confirm('Delete this message?')) {
        return;
    }

    router.delete(route('messages.destroy', messageId), { preserveScroll: true });
};

const isOwnMessage = (message: MessagingMessage) =>
    props.currentUserId !== null && message.sender_id === props.currentUserId;

const scrollToBottom = () => {
    nextTick(() => {
        if (!containerRef.value) {
            return;
        }

        containerRef.value.scrollTop = containerRef.value.scrollHeight;
    });
};

watch(
    () => props.messages.length,
    () => scrollToBottom(),
    { immediate: true },
);
</script>

<template>
    <div ref="containerRef" class="flex-1 space-y-3 overflow-y-auto p-4">
        <div
            v-for="message in props.messages"
            :key="message.id"
            class="flex items-start gap-2"
            :class="isOwnMessage(message) ? 'justify-end' : 'justify-start'"
        >
            <DropdownMenu
                v-if="isOwnMessage(message) && (message.can?.update || message.can?.delete)"
            >
                <DropdownMenuTrigger
                    class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    :class="isOwnMessage(message) ? 'order-2' : 'order-1'"
                >
                    <MoreVertical class="h-4 w-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        v-if="message.can?.update"
                        @click="startEdit(message)"
                    >
                        <Pencil class="mr-2 h-4 w-4" />
                        <span>Edit</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-if="message.can?.delete"
                        class="text-red-500 focus:text-red-500"
                        @click="destroyMessage(message.id)"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        <span>Delete</span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <div
                class="max-w-[85%] rounded-2xl px-4 py-2 text-sm shadow-sm"
                :class="
                    isOwnMessage(message)
                        ? 'rounded-br-md bg-gradient-to-br from-violet-600 to-violet-700 text-white'
                        : 'rounded-bl-md border border-gray-200 bg-white text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100'
                "
            >
                <template v-if="editingId === message.id">
                    <textarea
                        v-model="editContent"
                        class="mb-2 min-h-[72px] w-full rounded-md border border-white/30 bg-white/10 px-2 py-1 text-white placeholder:text-white/60"
                        rows="3"
                    />
                    <div class="flex justify-end gap-2">
                        <Button size="sm" variant="secondary" type="button" @click="cancelEdit">
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            type="button"
                            class="bg-white text-violet-700 hover:bg-white/90"
                            @click="saveEdit(message.id)"
                        >
                            Save
                        </Button>
                    </div>
                </template>

                <template v-else>
                    <p class="whitespace-pre-wrap break-words">
                        {{ message.content }}
                    </p>
                </template>
            </div>
        </div>

        <div
            v-if="props.messages.length === 0"
            class="flex min-h-48 items-center justify-center rounded-xl border border-dashed border-gray-200 bg-white/60 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400"
        >
            No messages yet. Start the conversation below.
        </div>
    </div>
</template>
