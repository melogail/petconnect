<script setup lang="ts">
import { ref } from 'vue';
import { useIntersectionObserver } from '@vueuse/core';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    hasMore: boolean;
    isLoading: boolean;
}>();

const emit = defineEmits(['loadMore']);

const { t } = useTranslations();
const target = ref<HTMLElement | null>(null);

useIntersectionObserver(
    target,
    ([{ isIntersecting }]) => {
        if (isIntersecting && props.hasMore && !props.isLoading) {
            emit('loadMore');
        }
    },
    {
        rootMargin: '200px',
        // Avoid rapid re-entry storms while content is still settling.
        threshold: 0.1,
    },
);
</script>

<template>
    <div ref="target" class="w-full">
        <!-- Loading State -->
        <div v-show="isLoading" class="mt-8 flex justify-center py-4">
            <svg
                class="h-8 w-8 animate-spin text-primary"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
            </svg>
        </div>

        <!-- End of list State -->
        <div
            v-show="!hasMore"
            class="mt-8 py-4 text-center text-sm text-gray-400"
        >
            <slot name="no-more">{{ t('home.end_of_list') }}</slot>
        </div>
    </div>
</template>
