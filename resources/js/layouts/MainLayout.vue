<script setup lang="ts">
import NavBar from '@/components/web/NavBar.vue';
import Footer from '@/components/web/Footer.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <NavBar />
        <main class="flex-grow pb-20 md:pb-16">
            <slot />
        </main>
        <Footer />
    </div>
</template>

<style scoped></style>
