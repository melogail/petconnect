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

const messages = [
    {
        id: 1,
        sender: 'Sarah Johnson',
        preview: 'Hi there! I was wondering about the adoption process...',
        time: '10m ago',
        read: false,
        avatar: 'https://randomuser.me/api/portraits/women/44.jpg'
    },
    {
        id: 2,
        sender: 'Mike Peterson',
        preview: 'Thanks for the information about Max!',
        time: '2h ago',
        read: true,
        avatar: 'https://randomuser.me/api/portraits/men/32.jpg'
    },
    {
        id: 3,
        sender: 'Adoption Center',
        preview: 'Your application has been received',
        time: '1d ago',
        read: true,
        avatar: 'https://randomuser.me/api/portraits/lego/5.jpg'
    },
];

const unreadCount = messages.filter(m => !m.read).length;

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

const markAsRead = (id: number) => {
    const message = messages.find(m => m.id === id);
    if (message) {
        message.read = true;
    }
};

const markAllAsRead = () => {
    messages.forEach(m => m.read = true);
};

<template>
    <div class="relative" ref="dropdownRef">
        <button
            @click="toggleDropdown"
            class="relative p-2 text-gray-600 transition rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
            :class="{ 'text-violet-600 dark:text-violet-400': isOpen }"
            aria-label="Messages"
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
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-5l-5 5v-5z"
                />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute top-0 right-0 flex items-center justify-center w-4 h-4 text-xs font-semibold text-white bg-violet-500 rounded-full dark:bg-violet-400"
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
                class="absolute right-0 z-50 w-80 mt-2 overflow-hidden bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700"
            >
                <div class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100">Messages</h3>
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllAsRead"
                        class="text-sm font-medium text-violet-600 hover:text-violet-700 dark:text-violet-400 dark:hover:text-violet-300"
                    >
                        Mark all as read
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <div v-if="messages.length === 0" class="p-4 text-center text-gray-500 dark:text-gray-400">
                        No new messages
                    </div>

                    <div v-else>
                        <div
                            v-for="message in messages"
                            :key="message.id"
                            @click="markAsRead(message.id)"
                            class="flex items-start p-4 transition cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            :class="{ 'bg-gray-50 dark:bg-gray-700/30': !message.read }"
                        >
                            <img
                                :src="message.avatar"
                                :alt="message.sender"
                                class="flex-shrink-0 w-10 h-10 mr-3 rounded-full border border-gray-200 dark:border-gray-600"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                        {{ message.sender }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ message.time }}
                                    </p>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                    {{ message.preview }}
                                </p>
                            </div>
                            <div v-if="!message.read" class="mt-1 ml-2">
                                <span class="w-2 h-2 bg-violet-500 rounded-full"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-2 text-center bg-gray-50 dark:bg-gray-700/50">
                    <a
                        href="/messages"
                        class="text-sm font-medium text-violet-600 hover:text-violet-700 dark:text-violet-400 dark:hover:text-violet-300"
                    >
                        View all messages
                    </a>
                </div>
            </div>
        </Transition>
    </div>
</template>
