<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * The small uppercase heading legacy puts above every block inside the facts
 * card — personality traits, location, health, healthcare, extras — and above
 * the Quick Info tile grid that sits outside it.
 *
 * Extracted because the same six classes and the same violet 16px icon appear
 * five times across those blocks in legacy, and a sixth block would otherwise
 * copy them a sixth time. The heading level is a prop rather than a fixed `h4`:
 * the five in-card blocks sit under the card's own `h2` and take the `h3`
 * default, while `PetQuickInfo` is a standalone section and passes `h2`.
 *
 * ## The bottom margin is `mb-3` here and overridable, on purpose
 *
 * Legacy uses two spacings for one heading style: `mb-3` on the five in-card
 * blocks (`PetAbout.vue:28`, `PetLocation.vue:21`, `PetHealthInfo.vue:40` and
 * `:119` in petconnect-old) and `mb-4` above the Quick Info grid
 * (`PetStats.vue:102`). Collapsing them onto one value would move a block on
 * one side or the other, so the difference is kept rather than averaged away —
 * the in-card spacing is the default and the odd one out passes `class="mb-4"`.
 * `cn` (tailwind-merge) resolves the conflict deterministically, so exactly one
 * `mb-*` reaches the DOM; declaring `class` as a prop is what keeps it out of
 * the fallthrough attributes, where both would have survived and the winner
 * would have depended on stylesheet order.
 */
const { level = 'h3', class: className = undefined } = defineProps<{
    title: string;
    icon?: Component;
    level?: 'h2' | 'h3' | 'h4';
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <component
        :is="level"
        :class="
            cn(
                'text-muted-foreground mb-3 flex items-center gap-2 text-xs font-semibold tracking-widest uppercase',
                className,
            )
        "
    >
        <component
            :is="icon"
            v-if="icon"
            class="text-primary size-4"
            aria-hidden="true"
        />
        {{ title }}
    </component>
</template>
