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
                    toast: 'bg-white border border-gray-200 shadow-lg',
                    title: 'text-gray-900 font-semibold',
                    description: 'text-gray-600',
                    success: 'border-green-200',
                    error: 'border-red-200',
                }
            }"
    />
</template>

<style scoped>

</style>
