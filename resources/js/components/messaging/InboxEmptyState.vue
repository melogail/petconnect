<script setup lang="ts">
import { MessageSquare } from 'lucide-vue-next';
</script>

<template>
    <div
        class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/80 py-16 text-center dark:border-gray-700 dark:bg-gray-900/40"
    >
        <MessageSquare class="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
        <p class="text-gray-600 dark:text-gray-400">No conversations yet</p>
        <p class="mt-1 text-sm text-gray-500">
            Message someone from a pet listing or profile to start chatting.
        </p>
    </div>
</template>
