<script setup lang="ts">
import { computed } from 'vue';

/**
 * The red count that sits on the corner of a header control.
 *
 * Two callers — the messages menu and the notification bell — and the legacy
 * shell styled both, so this is the one place the recipe lives rather than two
 * copies drifting apart.
 *
 * ## The red is literal, on purpose
 *
 * Everything else in this port translates legacy's hardcoded `bg-white` /
 * `text-gray-*` into this app's tokens so dark mode works. `bg-red-500` is the
 * exception, and it is the exception phase 3a named: a colour that carries
 * meaning rather than a surface is kept exactly, because it has to read as
 * "unread" in both schemes and against every control it lands on. White on
 * red-500 is the same pair in light and dark, so there is nothing to translate.
 *
 * It is deliberately **not** `bg-primary`. That was the shipped notification
 * badge before this phase and it made the badge the same violet as the brand
 * accents around it — legibly present, but not legibly a count of things
 * needing attention. Legacy used red for both badges; so does this.
 *
 * ## `min-h-4 min-w-4` with `px-1`, not `h-4 w-4`
 *
 * Legacy spelled the two badges differently: the messages one grew
 * (`min-h-4 min-w-4 … px-1`) and the notification one was pinned
 * (`h-4 w-4`, no padding). Pinned is a bug at the only width that matters —
 * "9+" is two glyphs at `text-[10px]` and does not fit in 16px — so the growing
 * spelling is the one kept for both. A single digit still renders as a 16px
 * circle because the minimum is what binds it.
 *
 * Two smaller differences were harmonised the same way, and for the same
 * reason: legacy spelled the two badges differently and the messages one is the
 * spelling that already deals with `9+`, so it is the one both inherit. This
 * sits at `end-0.5 top-0.5` (2px) where legacy's bell badge sat at `end-1
 * top-1` (4px), and it is `font-semibold`, which legacy's bell badge did not
 * carry at all.
 *
 * ## Nine, not ninety-nine
 *
 * `9+` above 9 is legacy's ceiling for both badges and is what this returns.
 * The notification bell previously capped at `99+` on the reasoning that the
 * badge sits on a small control and a four-digit count would push it off the
 * header — which is true, and `9+` satisfies it more strictly. Two glyphs is
 * also the widest thing this badge ever has to hold, which is what lets the
 * geometry above be checked once instead of per count.
 *
 * It is `aria-hidden` everywhere it is used: the count already reaches
 * assistive technology through the trigger's accessible name, and announcing
 * a bare "3" beside "Messages" reads as a second, unexplained control.
 */
const { count } = defineProps<{ count: number }>();

const label = computed(() => (count > 9 ? '9+' : String(count)));
</script>

<template>
    <span
        v-if="count > 0"
        class="absolute end-0.5 top-0.5 flex min-h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] leading-none font-semibold text-white shadow-sm"
        aria-hidden="true"
    >
        {{ label }}
    </span>
</template>
