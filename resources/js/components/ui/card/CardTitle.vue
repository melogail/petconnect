<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/**
 * The card's name. Deviates from the shadcn-vue original in exactly one way —
 * the element is a prop — so re-running `shadcn-vue add card` will drop it.
 *
 * ## Why the heading level is the caller's to pick
 *
 * A heading level is a **document-level** property: it is well-formed or not as
 * a function of the whole assembled page, and a shared primitive sees exactly
 * one instance of itself. Hardcoding `h3` here took that decision on behalf of
 * every call site, and no call site could evaluate it. `PetSafetyTips` was the
 * case that surfaced it: an `h3` in the `pages/pets/Show.vue` sidebar, directly
 * after `PetOwnerCard`'s `h2`, so the safety block read as a **subsection of
 * the owner** rather than as its sibling. See that component for the outline.
 *
 * `h3` stays the default because it is what this component has always rendered,
 * so the prop is additive and no existing caller moves. The only other two call
 * sites pass nothing and keep their `h3`. `TwoFactorRecoveryCodes` is correct
 * as it stands — rendered on `/settings/security` 2026-09-06, its `h3 2FA
 * recovery codes` sits under `h2 Two-factor authentication`, and the page's
 * heading list is byte-identical before and after this change.
 * `layouts/auth/AuthCardLayout` has no importer anywhere in `resources/`, so
 * nothing mounts it and there is no outline to be wrong.
 *
 * ## Do not hand-write the heading at the call site instead
 *
 * It looks like the smaller change and it is not. `cn` is tailwind-merge, and
 * the merge is order-sensitive in a way that is invisible at the call site:
 * `cn('leading-none font-semibold', 'text-base')` — the exact call
 * `PetSafetyTips` makes — emits **`font-semibold text-base`**, with
 * `leading-none` *dropped*, because `text-base` carries a line-height and
 * tailwind-merge treats the two as conflicting. Read out of the DOM in Chrome,
 * 2026-09-06, on `/pets/1` in an isolated build: `class="font-semibold
 * text-base"`, computed `line-height: 24px`.
 *
 * A hand-written `<h2 class="text-base leading-none font-semibold">` — the
 * order anyone would write — resolves the other way and was measured at
 * `line-height: 16px`, shrinking the `card-header` from 42px to 34px. So the
 * call site would have to know the merge result to reproduce today's rendering,
 * which is precisely the coupling this primitive exists to hold.
 *
 * `data-slot="card-title"` survives on whatever element is rendered. Note for
 * anyone repeating the reasoning above: on this tree **nothing selects on it**
 * — `CardHeader`'s grid keys on `has-data-[slot=card-action]`, and `card-title`
 * appears in zero compiled CSS rules. Removing it was rendered as a control and
 * changed no box and no computed style. It is kept as a stable hook, not
 * because a layout currently depends on it.
 */
const props = withDefaults(
  defineProps<{
    /** The heading level this card's position in the page outline requires. */
    as?: "h1" | "h2" | "h3" | "h4" | "h5" | "h6"
    class?: HTMLAttributes["class"]
  }>(),
  { as: "h3" },
)
</script>

<template>
  <component
    :is="props.as"
    data-slot="card-title"
    :class="cn('leading-none font-semibold', props.class)"
  >
    <slot />
  </component>
</template>
