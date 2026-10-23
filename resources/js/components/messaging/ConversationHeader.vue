<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { show as showProfile } from '@/routes/profile';
import type { Conversation } from '@/types';
import { useTranslations } from '@/composables/useTranslations';

/** Who you are talking to, above the thread. */
const { conversation } = defineProps<{ conversation: Conversation }>();

const peer = computed(() => conversation.peer ?? null);

const { t } = useTranslations();
</script>

<template>
    <header class="border-border flex items-center gap-3 border-b px-4 py-3">
        <UserAvatar
            :name="peer?.name ?? t('messaging.conversation')"
            :avatar="peer?.avatar ?? null"
            class="size-10 shrink-0"
        />

        <div class="min-w-0">
            <Link
                v-if="peer"
                :href="showProfile(peer.id)"
                class="font-medium hover:underline"
            >
                {{ peer.name }}
            </Link>
            <p v-else class="font-medium">{{ t('messaging.conversation') }}</p>

            <p
                v-if="peer?.location"
                class="text-muted-foreground truncate text-xs"
            >
                {{ peer.location }}
            </p>
        </div>
    </header>
</template>
