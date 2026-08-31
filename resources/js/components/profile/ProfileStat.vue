<script setup lang="ts">
/** One "42 Listings" pair in the profile header's stat row. */
defineProps<{
    label: string;
    value: string | number;
}>();
</script>

<template>
    <div>
        <dt class="text-muted-foreground text-xs tracking-wide uppercase">
            {{ label }}
        </dt>
        <dd class="text-lg font-semibold">{{ value }}</dd>
        <slot />
    </div>
</template>
