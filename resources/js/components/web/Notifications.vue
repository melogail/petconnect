<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const handleClickOutside = (event: MouseEvent) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const notifications = [
    { id: 1, text: 'New message from Sarah', time: '2m ago', read: false, type: 'message' },
    { id: 2, text: 'Your pet listing was favorited', time: '1h ago', read: false, type: 'favorite' },
    { id: 3, text: 'Reminder: Vet appointment tomorrow', time: '3h ago', read: true, type: 'reminder' },
];

const unreadCount = notifications.filter(n => !n.read).length;

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

const markAsRead = (id: number) => {
    const notification = notifications.find(n => n.id === id);
    if (notification) {
        notification.read = true;
    }
};

const markAllAsRead = () => {
    notifications.forEach(n => n.read = true);
};
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <button
            @click="toggleDropdown"
            class="relative p-2 text-gray-600 transition rounded-lg hover:bg-gray-100"
            :class="{ 'text-violet-600': isOpen }"
            aria-label="Notifications"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute top-0 right-0 flex items-center justify-center w-4 h-4 text-xs font-semibold text-white bg-red-500 rounded-full"
            >
                {{ unreadCount }}
            </span>
        </button>

        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 z-50 w-80 mt-2 overflow-hidden bg-white border border-gray-200 rounded-lg shadow-lg"
            >
                <div class="flex items-center justify-between p-4 border-b border-gray-100">
                    <h3 class="font-medium text-gray-900">Notifications</h3>
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllAsRead"
                        class="text-sm font-medium text-violet-600 hover:text-violet-700"
                    >
                        Mark all as read
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <div v-if="notifications.length === 0" class="p-4 text-center text-gray-500">
                        No new notifications
                    </div>

                    <div
                        v-for="notification in notifications"
                        :key="notification.id"
                        @click="markAsRead(notification.id)"
                        class="p-4 transition cursor-pointer hover:bg-gray-50"
                        :class="{ 'bg-gray-50': !notification.read }"
                    >
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-0.5">
                                <div
                                    class="flex items-center justify-center w-8 h-8 rounded-full"
                                    :class="{
                                        'bg-violet-100 text-violet-600': notification.type === 'message',
                                        'bg-amber-100 text-amber-600': notification.type === 'favorite',
                                        'bg-blue-100 text-blue-600': notification.type === 'reminder'
                                    }"
                                >
                                    <svg
                                        v-if="notification.type === 'message'"
                                        class="w-4 h-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                    </svg>
                                    <svg
                                        v-else-if="notification.type === 'favorite'"
                                        class="w-4 h-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                    <svg
                                        v-else
                                        class="w-4 h-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-3 flex-1">
                                <p
                                    class="text-sm"
                                    :class="{ 'font-medium text-gray-900': !notification.read, 'text-gray-600': notification.read }"
                                >
                                    {{ notification.text }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">{{ notification.time }}</p>
                            </div>
                            <div v-if="!notification.read" class="ml-4">
                                <div class="w-2 h-2 bg-violet-500 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-2 text-center border-t border-gray-100">
                    <a href="/notifications" class="text-sm font-medium text-violet-600 hover:text-violet-700">
                        View all notifications
                    </a>
                </div>
            </div>
        </Transition>
    </div>
</template>
