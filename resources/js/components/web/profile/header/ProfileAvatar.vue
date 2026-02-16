<script setup lang="ts">
import { Edit2 } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    user: Object,
});
</script>

<template>
    <div class="group relative">
        <div
            class="absolute inset-0 rotate-3 scale-105 transform rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-500 opacity-60 transition-all duration-300 group-hover:opacity-80"
        ></div>
        <div
            v-if="user.data.profile_image"
            class="relative h-36 w-36 overflow-hidden rounded-2xl border-4 border-white shadow-xl dark:border-gray-800"
        >
            <img
                :src="user.data.profile_image"
                :alt="user.data.name"
                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            />
            <div
                class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
            ></div>
        </div>
        <div v-else>
            <div
                class="relative flex h-36 w-36 items-center justify-center overflow-hidden rounded-2xl border-4 border-white shadow-xl dark:border-gray-800"
            >
                <div
                    class="absolute inset-0 rotate-3 scale-105 transform bg-gradient-to-br from-indigo-400 to-violet-500 opacity-60 transition-all duration-300 group-hover:opacity-80"
                ></div>
                <span class="relative text-4xl font-bold uppercase text-white">
                    {{ user.data.name.slice(0, 2) }}
                </span>
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                ></div>
            </div>
        </div>
    </div>
</template>
