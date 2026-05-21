<script setup lang="ts">
import { useAuthUser } from '@/composables/useAuthUser';
import { fallbackAvatar, messagePreview } from '@/lib/utils';
import type { MessagingPreviewItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

defineProps<{
    previews: MessagingPreviewItem[];
    unreadCount: number;
    isOpen: boolean;
}>();

const currentUser = useAuthUser();
const currentUserId = computed(() => currentUser.value?.id ?? null);
const emit = defineEmits(['toggle']);
</script>

<template>
    <div class="relative">
        <button
            type="button"
            @click.stop="emit('toggle')"
            class="relative rounded-full p-2 transition-colors duration-200 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800"
            :aria-expanded="isOpen"
            :aria-label="`Messages ${unreadCount > 0 ? `(${unreadCount} unread)` : ''}`"
        >
            <span class="sr-only">Messages</span>
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-6 w-6 text-gray-600 transition-transform duration-200 dark:text-gray-300"
                :class="{ 'text-violet-600 dark:text-violet-400': isOpen }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute right-0.5 top-0.5 flex min-h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white shadow-sm"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                v-click-outside="() => emit('toggle')"
                class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-black/10 dark:bg-gray-800 dark:ring-white/10"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/80"
                >
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100"
                    >
                        <span>Messages</span>
                        <span
                            v-if="unreadCount > 0"
                            class="rounded-full bg-violet-100 px-2 py-0.5 text-xs text-violet-800 dark:bg-violet-900/50 dark:text-violet-200"
                        >
                            {{ unreadCount }} unread
                        </span>
                    </h3>
                    <button
                        type="button"
                        @click.stop="emit('toggle')"
                        class="text-xs font-medium text-violet-600 transition-colors hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300"
                    >
                        Close
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <TransitionGroup
                        v-if="previews.length"
                        name="list"
                        tag="div"
                    >
                        <Link
                            v-for="item in previews"
                            :key="item.conversation_id"
                            :href="route('conversations.show', item.conversation_id)"
                            class="group relative block border-b border-gray-100 px-4 py-3 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-700/50 dark:hover:bg-gray-700/50"
                            :class="{
                                'bg-violet-50 dark:bg-violet-900/20': item.unread,
                            }"
                            @click="emit('toggle')"
                        >
                            <div class="flex items-start gap-3">
                                <div class="relative flex-shrink-0">
                                    <img
                                        :src="
                                            item.peer.avatar ||
                                            fallbackAvatar(item.peer.name)
                                        "
                                        :alt="item.peer.name"
                                        class="h-10 w-10 rounded-full object-cover ring-2 ring-white dark:ring-gray-700"
                                        :class="{
                                            'ring-violet-400': item.unread,
                                        }"
                                    />
                                    <span
                                        v-if="item.unread"
                                        class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-white bg-violet-500 dark:border-gray-800"
                                    ></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="mb-0.5 flex items-center justify-between"
                                    >
                                        <p
                                            class="truncate text-sm font-medium text-gray-900 dark:text-gray-100"
                                            :class="{
                                                'font-semibold': item.unread,
                                            }"
                                        >
                                            {{ item.peer.name }}
                                        </p>
                                        <span
                                            class="ml-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400"
                                            >{{ item.time }}</span
                                        >
                                    </div>
                                    <p
                                        class="truncate text-sm text-gray-600 dark:text-gray-300"
                                        :class="{ 'font-medium': item.unread }"
                                    >
                                        {{ messagePreview(item.preview, item.sender_id === currentUserId) }}
                                    </p>
                                </div>
                            </div>
                        </Link>
                    </TransitionGroup>

                    <div v-else class="px-4 py-6 text-center">
                        <div class="mx-auto mb-3 h-12 w-12 text-gray-400">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                                />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No conversations yet
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            Reach out from a pet listing or profile
                        </p>
                    </div>
                </div>

                <div
                    v-if="previews.length > 0"
                    class="border-t border-gray-200 bg-gray-50 px-4 py-2 text-center dark:border-gray-700 dark:bg-gray-800/80"
                >
                    <Link
                        :href="route('conversations.index')"
                        class="text-xs font-medium text-violet-600 hover:underline dark:text-violet-400"
                        @click="emit('toggle')"
                    >
                        View all messages
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
    transition: all 0.3s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
</style>
