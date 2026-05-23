<script setup lang="ts">
import InboxList from '@/components/messaging/InboxList.vue';
import MainLayout from '@/layouts/MainLayout.vue';
import type { MessagingInboxRow } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Inbox } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    inbox: MessagingInboxRow[];
}>();

const unreadCount = computed(
    () => props.inbox.filter((row) => row.unread).length,
);
</script>

<template>
    <Head title="Messages" />

    <MainLayout>
        <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
            <!-- Header -->
            <div class="mb-6 flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/40"
                    >
                        <Inbox
                            class="h-5 w-5 text-violet-600 dark:text-violet-400"
                        />
                    </div>
                    <div>
                        <h1 class="text-foreground text-xl font-bold">
                            Messages
                        </h1>
                        <p class="text-muted-foreground text-sm">
                            {{
                                unreadCount > 0
                                    ? `${unreadCount} unread conversation${unreadCount === 1 ? '' : 's'}`
                                    : `${inbox.length} conversation${inbox.length === 1 ? '' : 's'}`
                            }}
                        </p>
                    </div>
                </div>

                <!-- Unread badge -->
                <span
                    v-if="unreadCount > 0"
                    class="rounded-full bg-violet-100 px-3 py-1 text-sm font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300"
                >
                    {{ unreadCount }} new
                </span>
            </div>

            <InboxList :inbox="inbox" />
        </div>
    </MainLayout>
</template>
