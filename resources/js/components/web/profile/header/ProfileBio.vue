<script setup lang="ts">
import { User, Info } from 'lucide-vue-next';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

defineProps({
    user: Object,
});
</script>

<template>
    <div
        v-if="user.data.bio || user.data.can.update"
        class="mt-6 rounded-xl border border-gray-100/50 bg-white/50 p-4 backdrop-blur-sm transition-colors hover:bg-white dark:border-gray-700/50 dark:bg-gray-800/50 dark:hover:bg-gray-700/70"
    >
        <div class="flex items-start">
            <div class="me-3 rounded-lg bg-yellow-50 p-2 dark:bg-yellow-900/30">
                <User class="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ t('profile.bio') }}
                </p>
                <p
                    v-if="user.data.bio"
                    class="leading-relaxed text-gray-500 dark:text-white"
                >
                    {{ user.data.bio }}
                </p>
                <p v-else class="leading-relaxed text-gray-500 dark:text-white">
                    <span class="flex items-center italic">
                        <Info class="me-2 h-4 w-4" />
                        {{ t('profile.bio_empty') }}
                    </span>
                </p>
            </div>
        </div>
    </div>
</template>
