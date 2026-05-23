<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    fallbackAvatar,
    formatMessageDateSeparator,
    formatMessageTime,
} from '@/lib/utils';
import type { MessagingMessage, User } from '@/types';
import { router } from '@inertiajs/vue3';
import { MoreVertical, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    messages: MessagingMessage[];
    currentUserId: number | null;
    currentUser?: User | null;
    peer?: { id: number; name: string; avatar: string | null } | null;
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

    router.delete(route('messages.destroy', messageId), {
        preserveScroll: true,
    });
};

const isOwnMessage = (message: MessagingMessage) =>
    props.currentUserId !== null && message.sender_id === props.currentUserId;

const getSenderAvatar = (message: MessagingMessage): string => {
    if (isOwnMessage(message)) {
        const avatar =
            props.currentUser?.avatar ?? props.currentUser?.avatar_url ?? null;
        const name = props.currentUser?.name ?? 'Me';
        return avatar || fallbackAvatar(name);
    }

    const avatar =
        message.sender?.avatar ??
        message.sender?.avatar_url ??
        props.peer?.avatar ??
        null;
    const name = message.sender?.name ?? props.peer?.name ?? 'User';
    return avatar || fallbackAvatar(name);
};

const getSenderName = (message: MessagingMessage): string => {
    if (isOwnMessage(message)) {
        return props.currentUser?.name ?? 'You';
    }
    return message.sender?.name ?? props.peer?.name ?? 'User';
};

interface MessageWithGroup {
    message: MessagingMessage;
    isFirst: boolean;
    isLast: boolean;
    showDateSeparator: boolean;
    dateSeparator: string;
}

const groupedMessages = computed((): MessageWithGroup[] => {
    return props.messages.map((message, index) => {
        const prev = props.messages[index - 1] ?? null;
        const next = props.messages[index + 1] ?? null;

        const prevSame = prev && prev.sender_id === message.sender_id;
        const nextSame = next && next.sender_id === message.sender_id;

        const msgDate = message.created_at
            ? new Date(message.created_at).toDateString()
            : null;
        const prevDate = prev?.created_at
            ? new Date(prev.created_at).toDateString()
            : null;
        const showDateSeparator = msgDate !== null && msgDate !== prevDate;

        return {
            message,
            isFirst: !prevSame,
            isLast: !nextSame,
            showDateSeparator,
            dateSeparator: showDateSeparator
                ? formatMessageDateSeparator(message.created_at)
                : '',
        };
    });
});

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
    <div ref="containerRef" class="flex-1 space-y-1 overflow-y-auto px-4 py-4">
        <template v-if="groupedMessages.length === 0">
            <div
                class="border-border bg-muted/30 text-muted-foreground flex min-h-48 items-center justify-center rounded-xl border border-dashed text-sm"
            >
                No messages yet. Start the conversation below.
            </div>
        </template>

        <template v-for="group in groupedMessages" :key="group.message.id">
            <!-- Date separator -->
            <div
                v-if="group.showDateSeparator"
                class="flex items-center gap-3 py-3"
            >
                <div class="bg-border h-px flex-1" />
                <span
                    class="bg-muted text-muted-foreground shrink-0 rounded-full px-3 py-0.5 text-xs font-medium"
                >
                    {{ group.dateSeparator }}
                </span>
                <div class="bg-border h-px flex-1" />
            </div>

            <!-- Message row -->
            <div
                class="flex flex-col"
                :class="[
                    isOwnMessage(group.message) ? 'items-end' : 'items-start',
                    group.isLast ? 'mb-2' : 'mb-0.5',
                ]"
            >
                <!-- Avatar + bubble row (items-end so avatar bottom = bubble bottom) -->
                <div
                    class="flex items-end gap-2"
                    :class="
                        isOwnMessage(group.message)
                            ? 'flex-row-reverse'
                            : 'flex-row'
                    "
                >
                    <!-- Avatar -->
                    <div class="shrink-0" style="width: 32px; height: 32px">
                        <img
                            v-if="group.isLast"
                            :src="getSenderAvatar(group.message)"
                            :alt="getSenderName(group.message)"
                            class="ring-background h-8 w-8 rounded-full object-cover ring-2"
                        />
                        <div v-else class="h-8 w-8" />
                    </div>

                    <!-- Bubble + action menu -->
                    <div
                        class="flex max-w-[75%] items-end gap-1.5"
                        :class="
                            isOwnMessage(group.message)
                                ? 'flex-row-reverse'
                                : 'flex-row'
                        "
                    >
                        <!-- Action menu for own messages -->
                        <DropdownMenu
                            v-if="
                                isOwnMessage(group.message) &&
                                (group.message.can?.update ||
                                    group.message.can?.delete)
                            "
                        >
                            <DropdownMenuTrigger
                                class="text-muted-foreground hover:bg-muted mb-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full opacity-0 transition-opacity hover:opacity-100 focus:opacity-100 group-hover:opacity-100"
                            >
                                <MoreVertical class="h-3.5 w-3.5" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    v-if="group.message.can?.update"
                                    @click="startEdit(group.message)"
                                >
                                    <Pencil class="mr-2 h-4 w-4" />
                                    <span>Edit</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="group.message.can?.delete"
                                    class="text-destructive focus:text-destructive"
                                    @click="destroyMessage(group.message.id)"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    <span>Delete</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Bubble -->
                        <div class="group flex flex-col">
                            <!-- Sender name for peer messages (only on first in group) -->
                            <span
                                v-if="
                                    !isOwnMessage(group.message) &&
                                    group.isFirst
                                "
                                class="text-muted-foreground mb-1 ml-1 text-xs font-medium"
                            >
                                {{ getSenderName(group.message) }}
                            </span>

                            <div
                                class="relative px-3.5 py-2 text-sm shadow-sm"
                                :class="[
                                    isOwnMessage(group.message)
                                        ? 'bg-violet-600 text-white'
                                        : 'bg-card text-card-foreground border-border border',
                                    group.isFirst && group.isLast
                                        ? 'rounded-2xl'
                                        : isOwnMessage(group.message)
                                          ? [
                                                group.isFirst
                                                    ? 'rounded-t-2xl rounded-bl-2xl rounded-br-md'
                                                    : '',
                                                !group.isFirst && !group.isLast
                                                    ? 'rounded-l-2xl rounded-r-md'
                                                    : '',
                                                group.isLast
                                                    ? 'rounded-b-2xl rounded-tl-2xl rounded-tr-md'
                                                    : '',
                                            ].join(' ')
                                          : [
                                                group.isFirst
                                                    ? 'rounded-t-2xl rounded-bl-md rounded-br-2xl'
                                                    : '',
                                                !group.isFirst && !group.isLast
                                                    ? 'rounded-l-md rounded-r-2xl'
                                                    : '',
                                                group.isLast
                                                    ? 'rounded-b-2xl rounded-tl-md rounded-tr-2xl'
                                                    : '',
                                            ].join(' '),
                                ]"
                            >
                                <!-- Edit mode -->
                                <template v-if="editingId === group.message.id">
                                    <textarea
                                        v-model="editContent"
                                        class="mb-2 min-h-[72px] w-full resize-none rounded-lg border border-white/30 bg-white/10 px-2 py-1 text-white placeholder:text-white/60 focus:outline-none focus:ring-2 focus:ring-white/50"
                                        rows="3"
                                    />
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            type="button"
                                            @click="cancelEdit"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            size="sm"
                                            type="button"
                                            class="bg-white text-violet-700 hover:bg-white/90"
                                            @click="saveEdit(group.message.id)"
                                        >
                                            Save
                                        </Button>
                                    </div>
                                </template>

                                <template v-else>
                                    <p
                                        class="whitespace-pre-wrap break-words leading-relaxed"
                                    >
                                        {{ group.message.content }}
                                    </p>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timestamp: outside the avatar+bubble row so avatar aligns with bubble bottom -->
                <span
                    v-if="group.isLast && group.message.created_at"
                    class="text-muted-foreground mt-1 text-[10px]"
                    :class="
                        isOwnMessage(group.message)
                            ? 'pr-10 text-right'
                            : 'pl-10 text-left'
                    "
                >
                    {{ formatMessageTime(group.message.created_at) }}
                </span>
            </div>
        </template>
    </div>
</template>
