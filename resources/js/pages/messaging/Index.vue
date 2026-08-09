<script setup lang="ts">
import InboxList from '@/components/messaging/InboxList.vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { MessagingInboxRow } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Inbox } from 'lucide-vue-next';
import { computed } from 'vue';

const { t } = useTranslations();

const props = defineProps<{
    inbox: MessagingInboxRow[];
}>();

const unreadCount = computed(
    () => props.inbox.filter((row) => row.unread).length,
);

function plural(key: string, count: number): string {
    const parts = t(key, { count }).split('|');

    return parts[count === 1 ? 0 : parts.length - 1] ?? parts[0] ?? '';
}

const subtitle = computed(() =>
    unreadCount.value > 0
        ? plural('messaging.unread_conversations', unreadCount.value)
        : plural('messaging.conversations', props.inbox.length),
);
</script>

<template>
    <Head :title="t('messaging.messages')" />

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
                            {{ t('messaging.messages') }}
                        </h1>
                        <p class="text-muted-foreground text-sm">
                            {{ subtitle }}
                        </p>
                    </div>
                </div>

                <!-- Unread badge -->
                <span
                    v-if="unreadCount > 0"
                    class="rounded-full bg-violet-100 px-3 py-1 text-sm font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300"
                >
                    {{ t('messaging.new', { count: unreadCount }) }}
                </span>
            </div>

            <InboxList :inbox="inbox" />
        </div>
    </MainLayout>
</template>
