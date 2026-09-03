<script setup lang="ts">
/**
 * One titled block of the filter sheet. Four of them, all identical chrome, so
 * the heading's type scale is written once rather than four times.
 */
defineProps<{ title: string }>();
</script>

<template>
    <section>
        <h3
            class="text-muted-foreground mb-4 text-sm font-semibold tracking-wide uppercase"
        >
            {{ title }}
        </h3>
        <slot />
    </section>
</template>
