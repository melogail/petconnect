<script setup lang="ts">
import { ChevronDown } from "@lucide/vue";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";

/**
 * One question, with its answer in the default slot.
 *
 * The house `Collapsible` (reka-ui) rather than a native `<details>`: reka
 * measures the content and exposes `--reka-collapsible-content-height`, which
 * is what `animate-collapsible-down/up` from tw-animate-css animate, so the
 * answer slides open and closed instead of snapping. Keyboard operation and
 * the `aria-expanded` wiring come with the primitive.
 */
defineProps<{ question: string }>();
</script>

<template>
    <Collapsible
        class="border-border bg-card shadow-card group rounded-[20px] border"
    >
        <CollapsibleTrigger
            class="hover:bg-accent/40 focus-visible:ring-ring focus-visible:ring-offset-background flex w-full cursor-pointer items-center justify-between gap-3 rounded-[20px] p-4 text-start font-medium transition-[background-color,border-color,box-shadow,transform] duration-200 ease-out outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
        >
            {{ question }}
            <ChevronDown
                class="text-muted-foreground size-5 shrink-0 transition-transform duration-200 group-data-[state=open]:rotate-180"
                aria-hidden="true"
            />
        </CollapsibleTrigger>

        <CollapsibleContent
            class="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down"
        >
            <div class="text-muted-foreground px-4 pb-4 text-sm">
                <slot />
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
