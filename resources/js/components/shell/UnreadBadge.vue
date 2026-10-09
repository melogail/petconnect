<script setup lang="ts">
import { computed } from 'vue';
import { unreadBadgeLabel } from '@/components/shell/labels';

/**
 * The unread count that sits on the corner of a header control.
 *
 * Two callers — the messages menu and the notification bell — and the legacy
 * shell styled both, so this is the one place the recipe lives rather than two
 * copies drifting apart.
 *
 * ## Brand violet with a page-coloured ring, not red
 *
 * This was `bg-red-500` through the port, kept literal as the one colour that
 * carried meaning rather than a surface. The "Aubergine & Lavender" guide
 * (`.claude/PETCONNECT-STYLE-GUIDE.md` §1, §5.3) retires every hue but the
 * brand violet, and names the header's unread marker as `bg-primary` with
 * `ring-2 ring-background`. The ring is what does the job red used to do: a
 * 2px cut-out of page colour separates the badge from the glyph it overlaps,
 * so it reads as "a thing on top of the control" in both schemes without a
 * second hue. `--primary-foreground` on `--primary` measures 6.2:1 light and
 * 9.6:1 dark.
 *
 * The guide's §5.3 draws this as a bare 7px dot. The count is kept instead,
 * because the restyle's scope statement — every accessibility contract stays
 * exactly as it is — outranks one line of the component spec: the visible
 * `9+` is what `shell/labels`' `nameContaining` exists to keep inside the
 * triggers' accessible names (WCAG 2.5.3), and a dot would make that logic
 * dead surface.
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
 * The cap now lives in `shell/labels`' `unreadBadgeLabel` rather than in this
 * file, and it moved because **both triggers have to know what this badge is
 * rendering**: above nine the string on screen ("9+") and the string in the
 * trigger's accessible name ("89 unread notifications") are different, and
 * WCAG 2.5.3 is about the first. Two copies of `count > 9` would let the cap
 * and the name drift apart with nothing failing. This still renders it; it no
 * longer owns it.
 *
 * It is `aria-hidden` everywhere it is used, and that is unchanged: the count
 * reaches assistive technology through the trigger's accessible name, and
 * announcing a bare "3" beside "Messages" reads as a second, unexplained
 * control. `aria-hidden` keeps it out of the *name computation*; it does not
 * make the glyphs invisible, which is why the trigger still has to contain
 * them — see `shell/labels`' `nameContaining`.
 */
const { count } = defineProps<{ count: number }>();

const label = computed(() => unreadBadgeLabel(count));
</script>

<template>
    <span
        v-if="count > 0"
        class="bg-primary text-primary-foreground ring-background absolute end-0.5 top-0.5 flex min-h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-semibold ring-2"
        aria-hidden="true"
    >
        {{ label }}
    </span>
</template>
