<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Notifications from '@/components/web/Notifications.vue';
import Messages from '@/components/web/Messages.vue';

const page = usePage();
const isDropdownOpen = ref(false);
const dropdownRef = ref(null);

const user = computed(() => page.props.auth.user);
const isLoggedIn = computed(() => !!user.value);

const getInitials = (name) => {
    if (!name) return 'U';
    return name
        .split(' ')
        .slice(0, 2)
        .map((n) => n[0])
        .join('')
        .toUpperCase();
};

const toggleDropdown = () => {
    isDropdownOpen.value = !isDropdownOpen.value;
};

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <nav
        class="sticky top-0 z-50 border-b border-gray-200 bg-white/80 px-6 py-3 backdrop-blur-md transition-all duration-200"
    >
        <div class="mx-auto flex max-w-7xl items-center justify-between">
            <!-- Logo Section -->
            <Link href="/" class="flex items-center gap-2.5">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-500"
                >
                    <div class="h-6 w-6 rounded-full bg-white"></div>
                </div>
                <span class="text-xl font-bold text-gray-800">PetConnect</span>
            </Link>

            <!-- Search Bar -->
            <div class="mx-8 max-w-xl flex-1">
                <div class="relative">
                    <svg
                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 transform text-gray-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <circle cx="11" cy="11" r="8" stroke-width="2" />
                        <path
                            d="m21 21-4.35-4.35"
                            stroke-width="2"
                            stroke-linecap="round"
                        />
                    </svg>
                    <input
                        type="text"
                        placeholder="Search for pets, breeds, or locations..."
                        class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pr-4 pl-11 text-sm focus:border-violet-300 focus:outline-none focus:ring-2 focus:ring-violet-200"
                    />
                </div>
            </div>

            <!-- Right Section -->
            <div class="flex items-center gap-3">
                <!-- Show auth links when not logged in -->
                <div v-if="!isLoggedIn" class="flex items-center gap-2">
                    <Link
                        href="/login"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 transition hover:text-violet-600"
                    >
                        Log in
                    </Link>
                    <Link
                        href="/register"
                        class="rounded-lg bg-violet-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-violet-600"
                    >
                        Sign up
                    </Link>
                </div>

                <!-- Show user controls when logged in -->
                <template v-else>
                    <!-- Notifications -->
                    <Notifications />

                    <!-- Messages -->
                    <Messages />

                    <!-- Dark Mode Toggle -->
                    <button class="rounded-lg p-2 transition hover:bg-gray-100">
                        <svg
                            class="h-5 w-5 text-gray-600"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </button>

                    <!-- User Avatar with Dropdown -->
                    <div class="relative" ref="dropdownRef">
                        <button
                            @click="toggleDropdown"
                            class="flex items-center gap-1.5 rounded-lg p-1 transition hover:bg-gray-100"
                        >
                            <div v-if="user.avatar_url" class="relative">
                                <img
                                    :src="user.avatar_url"
                                    :alt="user.name"
                                    class="h-8 w-8 rounded-full border-2 border-violet-500 object-cover"
                                />
                            </div>
                            <div
                                v-else
                                class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-violet-500 bg-violet-100 text-sm font-medium text-violet-700"
                            >
                                {{ getInitials(user.name) }}
                            </div>
                            <svg
                                class="h-3.5 w-3.5 text-gray-600 transition-transform"
                                :class="{ 'rotate-180': isDropdownOpen }"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    d="m6 9 6 6 6-6"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <Transition
                            enter-active-class="transition ease-out duration-100"
                            enter-from-class="transform opacity-0 scale-95"
                            enter-to-class="transform opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="transform opacity-100 scale-100"
                            leave-to-class="transform opacity-0 scale-95"
                        >
                            <div
                                v-if="isDropdownOpen"
                                class="absolute right-0 z-50 mt-2 w-48 rounded-lg border border-gray-200 bg-white py-1.5 shadow-lg"
                            >
                                <div class="border-b border-gray-100 px-4 py-2">
                                    <p class="truncate capitalize text-sm font-medium text-gray-900">
                                        {{ user.name }}
                                    </p>
                                    <p class="truncate text-xs text-gray-500">
                                        {{ user.email }}
                                    </p>
                                </div>
                                <Link
                                    href="/profile"
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50 hover:text-violet-600"
                                >
                                    Profile
                                </Link>
                                <Link
                                    href="/dashboard"
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50 hover:text-violet-600"
                                >
                                    Dashboard
                                </Link>
                                <div class="my-1 border-t border-gray-100"></div>

                                <!-- Logout using Link component -->
                                <Link
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    class="block w-full px-4 py-2 text-left text-sm text-red-600 transition hover:bg-red-50"
                                    @click="isDropdownOpen = false"
                                >
                                    Sign out
                                </Link>
                            </div>
                        </Transition>
                    </div>
                </template>
            </div>
        </div>
    </nav>
</template>
