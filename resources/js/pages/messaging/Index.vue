<script setup lang="ts">
import InboxList from '@/components/messaging/InboxList.vue';
import MainLayout from '@/layouts/MainLayout.vue';
import type { MessagingInboxRow } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    inbox: MessagingInboxRow[];
}>();

const unreadCount = computed(() => props.inbox.filter((row) => row.unread).length);
</script>

<template>
    <Head title="Messages" />

    <MainLayout>
        <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Messages
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        unreadCount > 0
                            ? `${unreadCount} unread conversation${unreadCount === 1 ? '' : 's'}`
                            : 'Conversations with other members'
                    }}
                </p>
            </div>

            <InboxList :inbox="inbox" />
        </div>
    </MainLayout>
</template>
