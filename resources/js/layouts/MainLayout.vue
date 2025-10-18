<script setup lang="ts">

import NavBar from '@/components/web/NavBar.vue';
import Footer from '@/components/web/Footer.vue';
import { Toaster } from 'vue-sonner';

</script>

<template>
    <NavBar />
    <slot />
    <Footer />
    <!-- Toaster component -->
    <Toaster
        position="top-center"
        :toastOptions="{
                duration: 3000,
                classNames: {
                    toast: 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-lg',
                    title: 'text-gray-900 dark:text-gray-100 font-semibold',
                    description: 'text-gray-600 dark:text-gray-300',
                    success: 'border-green-200 dark:border-green-800',
                    error: 'border-red-200 dark:border-red-800',
                    info: 'border-blue-200 dark:border-blue-800',
                    warning: 'border-yellow-200 dark:border-yellow-800',
                },
                unstyled: false,
                class: 'group',
                description: 'group-[.toaster]:text-sm',
                actionButton: 'group-[.toast]:bg-primary group-[.toast]:text-primary-foreground',
                cancelButton: 'group-[.toast]:bg-muted group-[.toast]:text-muted-foreground',
            }"
    />
</template>

<style scoped>

</style>
