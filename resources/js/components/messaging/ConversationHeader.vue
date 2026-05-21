<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ArrowLeft } from 'lucide-vue-next';

defineProps<{
    peerName: string;
    peerAvatar: string;
    subtitle?: string;
}>();
</script>

<template>
    <div class="mb-4 flex items-center gap-3">
        <Link
            :href="route('conversations.index')"
            class="rounded-full p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
        >
            <ArrowLeft class="h-5 w-5" />
            <span class="sr-only">Back to inbox</span>
        </Link>
        <img
            :src="peerAvatar"
            :alt="peerName"
            class="h-10 w-10 rounded-full object-cover ring-2 ring-violet-100 dark:ring-violet-900"
        />
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ peerName }}
            </h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ subtitle ?? 'Direct message' }}
            </p>
        </div>
    </div>
</template>
