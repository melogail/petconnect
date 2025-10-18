<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useDarkMode } from '@/composables/useDarkMode';
import NotificationsDropdown from '@/components/web/NotificationsDropdown.vue';
import MessagesDropdown from '@/components/web/MessagesDropdown.vue';
import UserDropdown from '@/components/web/UserDropdown.vue';

// Dark mode composable
const { isDark, toggleDarkMode } = useDarkMode();

// Inertia page props
const page = usePage();

// Dropdown states
const isUserDropdownOpen = ref(false);

// Dummy data (replace with API/inertia data)
const notifications = ref([
    { id: 1, text: 'New message from Sarah', time: '2m ago', read: false, type: 'message' },
    { id: 2, text: 'Your pet listing was favorited', time: '1h ago', read: false, type: 'favorite' },
    { id: 3, text: 'Reminder: Vet appointment tomorrow', time: '3h ago', read: true, type: 'reminder' }
]);

const messages = ref([
    {
        id: 1,
        sender: { name: 'Sarah Johnson', avatar: 'https://randomuser.me/api/portraits/women/44.jpg' },
        preview: 'Hey! Is your Golden Retriever still available for...',
        time: '2m ago',
        read: false
    },
    {
        id: 2,
        sender: { name: 'Michael Chen', avatar: 'https://randomuser.me/api/portraits/men/32.jpg' },
        preview: 'Thanks for the information about the adoption...',
        time: '1h ago',
        read: true
    }
]);

// UI state
const showNotifications = ref(false);
const showMessages = ref(false);

// Toggle logic
const toggleNotifications = () => {
    showNotifications.value = !showNotifications.value;
    if (showNotifications.value) showMessages.value = false;
};

const toggleMessages = () => {
    showMessages.value = !showMessages.value;
    if (showMessages.value) showNotifications.value = false;
};

// Mark as read handlers
const markNotificationAsRead = (id: number) => {
    const n = notifications.value.find(n => n.id === id);
    if (n) n.read = true;
};

const markAllNotificationsAsRead = () => {
    notifications.value.forEach(n => (n.read = true));
};

const markMessageAsRead = (id: number) => {
    const m = messages.value.find(m => m.id === id);
    if (m) m.read = true;
};

// Computed
const user = computed(() => page.props.auth.user);
const isLoggedIn = computed(() => !!user.value);
const unreadNotifications = computed(() => notifications.value.filter(n => !n.read).length);
const unreadMessages = computed(() => messages.value.filter(m => !m.read).length);

// Helpers
const getInitials = (name: string | undefined) => {
    if (!name) return 'U';
    return name
        .split(' ')
        .slice(0, 2)
        .map(n => n[0])
        .join('')
        .toUpperCase();
};

// Toggle user dropdown
const toggleUserDropdown = () => {
    isUserDropdownOpen.value = !isUserDropdownOpen.value;
    if (isUserDropdownOpen.value) {
        showNotifications.value = false;
        showMessages.value = false;
    }
};

// Close menus on outside click
const handleClickOutside = (e: MouseEvent) => {
    const target = e.target as Node;
    if (!target.closest('.user-dropdown')) isUserDropdownOpen.value = false;
    if (!target.closest('.notifications-container')) showNotifications.value = false;
    if (!target.closest('.messages-container')) showMessages.value = false;
};

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <nav class="sticky top-0 z-50 border-b border-gray-200 bg-white/80 px-6 py-3 backdrop-blur-md dark:border-gray-700 dark:bg-gray-900/80 shadow-sm transition-all">
        <div class="mx-auto flex max-w-7xl items-center justify-between">
            <!-- Logo -->
            <Link href="/" class="flex items-center gap-2.5">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-500">
                    <div class="h-6 w-6 rounded-full bg-white"></div>
                </div>
                <span class="text-xl font-bold text-gray-800 dark:text-gray-100">PetConnect</span>
            </Link>

            <!-- Search -->
            <div class="mx-8 max-w-xl flex-1 hidden md:block">
                <div class="relative">
                    <svg class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 transform text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle cx="11" cy="11" r="8" stroke-width="2" />
                        <path d="m21 21-4.35-4.35" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    <input
                        type="text"
                        placeholder="Search for pets, breeds, or locations..."
                        class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pr-4 pl-11 text-sm focus:border-violet-400 focus:ring-2 focus:ring-violet-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    />
                </div>
            </div>

            <!-- Right side -->
            <div class="flex items-center gap-3">
                <!-- Dark mode toggle -->
                <button @click="toggleDarkMode" class="relative inline-flex h-8 w-14 items-center rounded-full transition-colors focus:outline-none"
                        :class="isDark ? 'bg-violet-600' : 'bg-gray-200'">
          <span class="inline-block h-6 w-6 transform rounded-full bg-white dark:bg-gray-600 shadow-lg transition-transform duration-200 ease-in-out"
                :class="isDark ? 'translate-x-7' : 'translate-x-1'">
            <svg class="absolute h-5 w-5 text-gray-800" :class="isDark ? 'opacity-0' : 'opacity-100'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg class="absolute h-5 w-5 text-yellow-300" :class="isDark ? 'opacity-100' : 'opacity-0'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
                </button>

                <!-- If logged out -->
                <template v-if="!isLoggedIn">
                    <Link href="/login" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-violet-600">Log in</Link>
                    <Link href="/register" class="rounded-lg bg-violet-500 px-4 py-2 text-sm font-medium text-white hover:bg-violet-600">Sign up</Link>
                </template>

                <!-- If logged in -->
                <template v-else>
                    <!-- Notifications -->
                    <div class="notifications-container">
                        <NotificationsDropdown
                            :notifications="notifications"
                            :unread-count="unreadNotifications"
                            :is-open="showNotifications"
                            @toggle="toggleNotifications"
                            @mark-as-read="markNotificationAsRead"
                            @mark-all-as-read="markAllNotificationsAsRead"
                        />
                    </div>

                    <!-- Messages -->
                    <div class="messages-container">
                        <MessagesDropdown
                            :messages="messages"
                            :unread-count="unreadMessages"
                            :is-open="showMessages"
                            @toggle="toggleMessages"
                            @mark-as-read="markMessageAsRead"
                        />
                    </div>

                    <!-- User dropdown -->
                    <div class="user-dropdown">
                        <UserDropdown 
                            :user="user" 
                            :is-open="isUserDropdownOpen"
                            @toggle="toggleUserDropdown"
                            @update:is-open="isUserDropdownOpen = $event"
                        />
                    </div>
                </template>
            </div>
        </div>
    </nav>
</template>
