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
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <button
            @click="toggleDropdown"
            class="relative p-2 text-gray-600 transition rounded-lg hover:bg-gray-100"
            :class="{ 'text-violet-600': isOpen }"
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
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute top-0 right-0 flex items-center justify-center w-4 h-4 text-xs font-semibold text-white bg-violet-500 rounded-full"
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
                    <h3 class="font-medium text-gray-900">Messages</h3>
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllAsRead"
                        class="text-sm font-medium text-violet-600 hover:text-violet-700"
                    >
                        Mark all as read
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <div v-if="messages.length === 0" class="p-4 text-center text-gray-500">
                        No new messages
                    </div>

                    <a
                        v-for="message in messages"
                        :key="message.id"
                        href="#"
                        @click.prevent="markAsRead(message.id)"
                        class="block p-4 transition border-b border-gray-100 hover:bg-gray-50 last:border-b-0"
                        :class="{ 'bg-gray-50': !message.read }"
                    >
                        <div class="flex">
                            <div class="flex-shrink-0 mr-3">
                                <img
                                    :src="message.avatar"
                                    :alt="message.sender"
                                    class="w-10 h-10 rounded-full"
                                />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <p
                                        class="text-sm font-medium truncate"
                                        :class="{ 'text-gray-900': !message.read, 'text-gray-600': message.read }"
                                    >
                                        {{ message.sender }}
                                    </p>
                                    <p class="ml-2 text-xs text-gray-500 whitespace-nowrap">
                                        {{ message.time }}
                                    </p>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 truncate">
                                    {{ message.preview }}
                                </p>
                            </div>
                            <div v-if="!message.read" class="ml-4">
                                <div class="w-2 h-2 bg-violet-500 rounded-full"></div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="p-2 text-center border-t border-gray-100">
                    <a href="/messages" class="text-sm font-medium text-violet-600 hover:text-violet-700">
                        View all messages
                    </a>
                </div>
            </div>
        </Transition>
    </div>
</template>
