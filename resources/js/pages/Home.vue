<script setup lang="ts">

import MainLayout from '@/layouts/MainLayout.vue';
import Filter from '@/components/web/Filter.vue';
</script>

<template>
<MainLayout>
    <div class="content grid grid-cols-12">
        <!-- Filter -->
        <div class="col-span-12 lg:col-span-3 border-b border-r border-gray-200 bg-white">
            <Filter />
        </div>

        <!-- Cards -->
        <div class="col-span-12 lg:col-span-9 border-b border-gray-200 bg-white px-6 py-4">
            Here is the cards
        </div>
    </div>
</MainLayout>
</template>

<style scoped>

</style>
