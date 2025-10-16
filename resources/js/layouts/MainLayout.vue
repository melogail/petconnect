<script setup lang="ts">

import NavBar from '@/components/web/NavBar.vue';
import Footer from '@/components/web/Footer.vue';
</script>

<template>
    <NavBar />
    <slot />
    <Footer />
</template>

<style scoped>

</style>
