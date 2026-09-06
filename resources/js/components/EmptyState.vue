<script setup lang="ts">
import type { Component } from 'vue';

/** The "there is nothing here yet" block, used wherever a list can be empty. */
defineProps<{
    icon?: Component;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="border-border flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-12 text-center"
    >
        <component
            :is="icon"
            v-if="icon"
            class="text-muted-foreground size-8"
            aria-hidden="true"
        />
        <p class="font-medium">{{ title }}</p>
        <p v-if="description" class="text-muted-foreground max-w-sm text-sm">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
