<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

interface User {
    id: number;
    name: string;
    email?: string;
    avatar_url?: string;
}

const props = defineProps<{
    user: User;
    isOpen: boolean;
}>();

const emit = defineEmits(['toggle', 'update:isOpen']);

const toggleDropdown = () => {
    emit('toggle');
    emit('update:isOpen', !props.isOpen);
};

const closeDropdown = () => {
    if (props.isOpen) {
        emit('update:isOpen', false);
    }
};

// Close dropdown when clicking outside
const dropdownRef = ref<HTMLElement | null>(null);
const handleClickOutside = (e: MouseEvent) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    // Close dropdown when navigating away
    window.addEventListener('popstate', closeDropdown);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('popstate', closeDropdown);
});

// Get user initials for avatar fallback
const getInitials = (name: string) => {
    if (!name) return 'U';
    return name
        .split(' ')
        .slice(0, 2)
        .map((n) => n[0])
        .join('')
        .toUpperCase();
};
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <button
            @click.stop="toggleDropdown"
            type="button"
            class="flex items-center gap-2 rounded-full p-1 transition-all hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800"
            :class="{ 'bg-gray-100 dark:bg-gray-700': isOpen }"
            :aria-expanded="isOpen"
            :aria-label="`User menu for ${user.name}`"
        >
            <div class="relative">
                <img
                    v-if="user.avatar_url"
                    :src="user.avatar_url"
                    :alt="user.name"
                    class="h-8 w-8 rounded-full border-2 border-violet-500 object-cover dark:border-violet-400"
                />
                <div
                    v-else
                    class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-violet-500 bg-gradient-to-br from-violet-500 to-violet-600 text-sm font-medium text-white dark:border-violet-400"
                >
                    {{ getInitials(user.name) }}
                </div>
                <!-- Online status indicator -->
                <span
                    class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-500 dark:border-gray-800"
                ></span>
            </div>

            <span
                class="hidden text-sm font-medium text-gray-700 dark:text-gray-200 sm:block"
            >
                {{ user.name.split(' ')[0] }}
            </span>

            <svg
                class="h-3.5 w-3.5 text-gray-600 transition-transform duration-200 dark:text-gray-300"
                :class="{ 'rotate-180': isOpen }"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="m19 9-7 7-7-7"
                />
            </svg>
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
                class="absolute right-0 z-50 mt-2 w-56 origin-top-right divide-y divide-gray-100 rounded-md bg-white shadow-lg ring-1 ring-black/5 dark:divide-gray-700 dark:bg-gray-800 dark:ring-white/10"
            >
                <div class="px-4 py-3">
                    <p class="text-sm text-gray-900 dark:text-white">
                        {{ user.name }}
                    </p>
                    <p
                        class="truncate text-xs font-medium text-gray-500 dark:text-gray-400"
                    >
                        {{ user.email }}
                    </p>
                </div>

                <div class="py-1">
                    <Link
                        :href="route('conversations.index')"
                        class="group flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/50"
                        @click="closeDropdown"
                    >
                        <svg
                            class="h-4 w-4 text-gray-500 group-hover:text-violet-500"
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
                        <span>Messages</span>
                    </Link>
                    <Link
                        :href="route('profile.show', user.id)"
                        class="group flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/50"
                        @click="closeDropdown"
                    >
                        <svg
                            class="h-4 w-4 text-gray-500 group-hover:text-violet-500"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                            />
                        </svg>
                        <span>Your Profile</span>
                    </Link>
                </div>

                <div class="py-1">
                    <Link
                        href="/help"
                        class="group flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/50"
                        @click="closeDropdown"
                    >
                        <svg
                            class="h-4 w-4 text-gray-500 group-hover:text-violet-500"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <span>Help & Support</span>
                    </Link>
                    <a
                        href="#"
                        class="group flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/50"
                    >
                        <svg
                            class="h-4 w-4 text-gray-500 group-hover:text-violet-500"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <span>Help Center</span>
                    </a>
                </div>

                <div class="py-1">
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="group flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                        @click="closeDropdown"
                    >
                        <svg
                            class="h-4 w-4 text-red-500 group-hover:text-red-600 dark:text-red-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                            />
                        </svg>
                        <span>Sign out</span>
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* Add any custom styles here if needed */
</style>
