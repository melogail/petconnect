<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

const isDropdownOpen = ref(false);
const dropdownRef = ref(null);

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
    <nav class="sticky top-0 z-50 border-b border-gray-200 bg-white/80 px-6 py-3 backdrop-blur-md transition-all duration-200">
        <div class="mx-auto flex max-w-7xl items-center justify-between">
            <!-- Logo Section -->
            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-500"
                >
                    <div class="h-6 w-6 rounded-full bg-white"></div>
                </div>
                <span class="text-xl font-bold text-gray-800">PetConnect</span>
            </div>

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
                        class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pr-4 pl-11 text-sm"
                    />
                </div>
            </div>

            <!-- Right Section -->
            <div class="flex items-center gap-3">
                <!-- Notification Icon -->
                <button
                    class="relative rounded-lg p-2 transition hover:bg-gray-100"
                >
                    <svg
                        class="h-5 w-5 text-gray-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            d="M13.73 21a2 2 0 0 1-3.46 0M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <span
                        class="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-violet-500 text-[10px] font-semibold text-white"
                    >
                        3
                    </span>
                </button>

                <!-- Messages Icon -->
                <button
                    class="relative rounded-lg p-2 transition hover:bg-gray-100"
                >
                    <svg
                        class="h-5 w-5 text-gray-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <span
                        class="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-violet-500 text-[10px] font-semibold text-white"
                    >
                        5
                    </span>
                </button>

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
                        <img
                            src="https://api.dicebear.com/7.x/avataaars/svg?seed=Felix"
                            alt="User Avatar"
                            class="h-8 w-8 rounded-full border-2 border-violet-500"
                        />
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
                            class="absolute right-0 z-50 mt-2 w-44 rounded-lg border border-gray-200 bg-white py-1.5 shadow-lg"
                        >
                            <a
                                href="#profile"
                                class="block px-3.5 py-2 text-sm text-gray-700 transition hover:bg-violet-50 hover:text-violet-600"
                            >
                                Profile
                            </a>
                            <a
                                href="#dashboard"
                                class="block px-3.5 py-2 text-sm text-gray-700 transition hover:bg-violet-50 hover:text-violet-600"
                            >
                                Dashboard
                            </a>
                            <hr class="my-1.5 border-gray-200" />
                            <a
                                href="#logout"
                                class="block px-3.5 py-2 text-sm text-red-600 transition hover:bg-red-50"
                            >
                                Logout
                            </a>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </nav>
</template>

<style scoped></style>
