<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';

/**
 * One question, with its answer in the default slot.
 *
 * A native `<details>` rather than a `Collapsible`: this is a static page with
 * no state to coordinate, and `<details>` opens without JavaScript, is
 * keyboard-operable and is announced correctly by a screen reader for free.
 * The legacy rows were a `<button>` and a chevron with no handler at all, so
 * the answer was never anywhere.
 *
 * The summary carries `list-none` **and**
 * `[&::-webkit-details-marker]:hidden`: WebKit draws its disclosure triangle
 * through `::-webkit-details-marker` rather than through `list-style`, so
 * `list-none` on its own leaves Safari rendering the native triangle next to
 * this component's chevron — two disclosure affordances pointing opposite ways.
 */
defineProps<{ question: string }>();
</script>

<template>
    <details class="border-border bg-card group rounded-xl border">
        <summary
            class="hover:bg-accent/40 flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl p-4 font-medium [&::-webkit-details-marker]:hidden"
        >
            {{ question }}
            <ChevronDown
                class="text-muted-foreground size-5 shrink-0 transition-transform group-open:rotate-180"
                aria-hidden="true"
            />
        </summary>

        <div class="text-muted-foreground px-4 pb-4 text-sm">
            <slot />
        </div>
    </details>
</template>
