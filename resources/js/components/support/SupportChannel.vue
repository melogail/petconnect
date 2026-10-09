<script setup lang="ts">
import type { Component } from 'vue';

/**
 * One way of reaching the support team: an icon, a heading, what it is for,
 * and its action in the default slot.
 *
 * The action is a slot rather than an `href` prop because the two channels do
 * not act alike — one is a `mailto:` and one is not wired to anything yet — and
 * a component that took `href` would have to grow a second prop to say so.
 */
defineProps<{
    icon: Component;
    title: string;
    description: string;
}>();
</script>

<template>
    <div
        class="border-border bg-card shadow-card flex items-start gap-4 rounded-[20px] border p-4"
    >
        <span class="bg-secondary text-secondary-foreground rounded-xl p-3">
            <component :is="icon" class="size-6" aria-hidden="true" />
        </span>

        <div class="min-w-0 flex-1">
            <h3 class="font-serif text-lg font-medium">{{ title }}</h3>
            <p class="text-soft-foreground mt-1 text-sm">
                {{ description }}
            </p>

            <div class="mt-3">
                <slot />
            </div>
        </div>
    </div>
</template>
