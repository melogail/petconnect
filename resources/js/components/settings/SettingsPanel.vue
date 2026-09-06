<script setup lang="ts">
import Heading from '@/components/Heading.vue';

/** The titled section every settings panel sits in. */
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <section class="space-y-6">
        <Heading variant="small" :title="title" :description="description" />
        <slot />
    </section>
</template>
