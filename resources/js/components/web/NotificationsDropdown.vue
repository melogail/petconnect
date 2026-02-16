<script setup lang="ts">
import { ref } from 'vue';
import { TransitionGroup } from 'vue';

defineProps<{
    notifications: Array<{
        id: number;
        text: string;
        time: string;
        read: boolean;
        type: string;
    }>;
    unreadCount: number;
    isOpen: boolean;
}>();

const emit = defineEmits(['toggle', 'markAsRead', 'markAllAsRead']);

const markAsRead = (id: number) => emit('markAsRead', id);
const markAllAsRead = () => emit('markAllAsRead');

const getNotificationIcon = (type: string) => {
    const icons = {
        message:
            'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
        alert: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    };
    return icons[type as keyof typeof icons] || icons.info;
};
</script>

<template>
    <div class="relative">
        <button
            @click.stop="emit('toggle')"
            class="relative rounded-full p-2 transition-colors duration-200 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800"
            :aria-expanded="isOpen"
            :aria-label="`Notifications ${unreadCount > 0 ? `(${unreadCount} unread)` : ''}`"
        >
            <span class="sr-only">Notifications</span>
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
                    d="M15 17h5l-1.405-1.405C18.21 14.79 18 13.918 18 13V8a6 6 0 00-12 0v5c0 .918-.21 1.79-.595 2.595L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute right-1 top-1 flex h-4 w-4 animate-bounce items-center justify-center rounded-full bg-red-500 text-[10px] text-white shadow-sm"
                :class="{ 'animate-ping': unreadCount > 0 && !isOpen }"
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
                        <span>Notifications</span>
                        <span
                            v-if="unreadCount > 0"
                            class="rounded-full bg-violet-100 px-2 py-0.5 text-xs text-violet-800 dark:bg-violet-900/50 dark:text-violet-200"
                        >
                            {{ unreadCount }} unread
                        </span>
                    </h3>
                    <button
                        @click.stop="markAllAsRead"
                        class="text-xs font-medium text-violet-600 transition-colors hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300"
                        :disabled="unreadCount === 0"
                        :class="{
                            'cursor-not-allowed opacity-50': unreadCount === 0,
                        }"
                    >
                        Mark all as read
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <TransitionGroup
                        name="list"
                        tag="div"
                        v-if="notifications.length"
                    >
                        <div
                            v-for="n in notifications"
                            :key="n.id"
                            @click.stop="markAsRead(n.id)"
                            class="group relative cursor-pointer border-b border-gray-100 px-4 py-3 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-700/50 dark:hover:bg-gray-700/50"
                            :class="{
                                'bg-violet-50 dark:bg-violet-900/20': !n.read,
                            }"
                        >
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex-shrink-0">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-900/50"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4 text-violet-600 dark:text-violet-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                :d="getNotificationIcon(n.type)"
                                            />
                                        </svg>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="mb-0.5 text-sm font-medium leading-tight text-gray-800 dark:text-gray-100"
                                        :class="{ 'font-semibold': !n.read }"
                                    >
                                        {{ n.text }}
                                    </p>
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                            >{{ n.time }}</span
                                        >
                                        <span
                                            v-if="!n.read"
                                            class="h-2 w-2 rounded-full bg-violet-500"
                                        ></span>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                                    d="M15 17h5l-1.405-1.405C18.21 14.79 18 13.918 18 13V8a6 6 0 00-12 0v5c0 .918-.21 1.79-.595 2.595L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No new notifications
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            We'll let you know when there's something new.
                        </p>
                    </div>
                </div>

                <div
                    v-if="notifications.length > 0"
                    class="border-t border-gray-200 bg-gray-50 px-4 py-2 text-center dark:border-gray-700 dark:bg-gray-800/80"
                >
                    <a
                        href="#"
                        class="text-xs font-medium text-violet-600 hover:underline dark:text-violet-400"
                        >View all notifications</a
                    >
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
