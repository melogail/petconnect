<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

defineProps({});
</script>

<template>
    <!-- Header Section with Rich Gradient & Decor -->
    <div
        class="relative overflow-hidden bg-violet-600 pb-32 pt-16 shadow-xl dark:bg-gray-900 lg:pt-24"
    >
        <!-- Decorative Background Elements -->
        <div class="absolute inset-0">
            <div
                class="absolute inset-0 bg-gradient-to-br from-violet-500 via-purple-100 to-pink-100 opacity-90 mix-blend-multiply dark:from-violet-950 dark:via-gray-900 dark:to-gray-950 dark:mix-blend-normal"
            ></div>

            <!-- Abstract Circles/Shapes -->
            <div
                class="absolute -start-20 -top-20 h-96 w-96 rounded-full bg-white/20 blur-3xl filter"
            ></div>
            <div
                class="absolute -end-20 top-20 h-80 w-80 rounded-full bg-purple-300/20 blur-3xl filter"
            ></div>
            <div
                class="absolute bottom-0 left-1/2 h-64 w-64 -translate-x-1/2 transform rounded-full bg-white/10 blur-2xl filter"
            ></div>
        </div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <div
                    class="animate-in fade-in slide-in-from-bottom-4 mb-4 flex items-center gap-x-3 text-violet-100 duration-500"
                >
                    <span
                        class="inline-flex items-center rounded-md bg-white/20 px-2 py-1 text-xs font-medium text-white ring-1 ring-inset ring-white/30"
                    >
                        {{ t('profile.settings') }}
                    </span>
                    <span class="h-1 w-1 rounded-full bg-violet-200"></span>
                    <span class="text-sm font-medium">{{
                        t('profile.profile_management')
                    }}</span>
                </div>
                <h1
                    class="animate-in fade-in slide-in-from-bottom-5 text-4xl font-bold tracking-tight text-white duration-700 sm:text-5xl lg:text-6xl"
                >
                    {{ t('profile.edit_profile') }}
                </h1>
                <p
                    class="animate-in fade-in slide-in-from-bottom-6 mt-4 max-w-2xl text-lg leading-relaxed text-violet-50 duration-1000"
                >
                    {{ t('profile.edit_header_description') }}
                </p>
            </div>
        </div>
    </div>
</template>
