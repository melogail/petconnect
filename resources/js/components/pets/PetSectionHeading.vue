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
 * copy them a sixth time.
 *
 * ## Why the level is a prop, named against the consumers that pass each value
 *
 * One visual treatment sits at two depths on `pages/pets/Show.vue`, so the
 * element cannot be fixed at an `h4`:
 *
 * - **`h2`, passed** by `PetQuickInfo`, `PetLocationSection`,
 *   `PetHealthSection`'s first block and `PetExtras`. Each is a top-level
 *   section of the page, sibling to the `h2`s of `PetAboutSection`
 *   ("Meet Luna!"), `CommentThread` and `PetOwnerCard`, and under the listing
 *   name's `h1` in `PetDetailHeader`.
 * - **`h3`, the default**, taken by the two headings that really are nested:
 *   `PetAboutSection`'s "Personality Traits", inside that section's own `h2`,
 *   and `PetHealthSection`'s "Healthcare Details", inside the health block's.
 *
 * The `h2`s are **not** nested under a card heading, and this is the correction
 * to what stood here before: `PetFactsCard` renders a card, not a section — it
 * emits no heading of any level, which is checkable by reading its template —
 * so the "card's own `h2`" this docblock used to cite as the reason for the
 * `h3` default never existed. The consequence was silent and only visible in
 * the document outline: with the in-card blocks all at `h3`, Location, Health
 * and Extras read as **subsections of "Meet Luna!"**, the last `h2` above them,
 * rather than as siblings of it.
 *
 * Swapping the element is visually inert here: Tailwind's preflight resets
 * every heading's `font-size` and `font-weight` to `inherit`
 * (`node_modules/tailwindcss/preflight.css:74-86`, reached from
 * `resources/css/app.css:1`) and zeroes its margin, and the size, weight and
 * `mb-*` all come from the classes below. Established by reading preflight,
 * not by comparing screenshots.
 *
 * The outline itself was rendered, both ways, on `/pets/10` in an isolated
 * build served from a throwaway database, 2026-09-06: `h1 Mose`, `h2 Quick
 * Info`, `h2 Meet Mose!`, `h3 Personality Traits`, `h2 Location`, `h2 Health &
 * Veterinary`, `h2 Additional Information`, `h2 Comments`, `h2 <owner>`. With
 * the three `level="h2"` props reverted and nothing else changed, Location,
 * Health and Additional Information come back as `h3` — sitting under "Meet
 * Mose!" — which is the defect, reproduced rather than reasoned about.
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
