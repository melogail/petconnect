<script setup lang="ts">
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { formatRelative } from '@/lib/datetime';
import type { CommentPreview } from '@/types';

/**
 * One comment in the card's teaser. Read-only, and structurally so.
 *
 * The feed's preview rows carry **no `replies`** — `EagerLoadFeedRelations`
 * loads top-level comments only — so a thread cannot be expanded here without a
 * request of its own. Every affordance that would need one (reply, edit,
 * report, like) lives on the listing page, where the props that drive it are
 * shipped. Nothing here is a link either: a grid of twelve cards would
 * otherwise add thirty-six author links to the tab order to reach the first
 * card's actions.
 *
 * `author` is absent — not null — when a loader did not eager load it, so the
 * byline falls back rather than assuming a name. Same fallback string as
 * `comments/CommentBody.vue`, so the two read alike.
 *
 * `break-words` is on the `<li>`, not only on the body paragraph. Both the name
 * and the body are user-controlled strings, and the byline is a `<span>`, which
 * inherits `overflow-wrap: normal` and therefore does not break an unspaced
 * run.
 *
 * The width of such a run is a function of the glyph, not of the character
 * count, so the figure only means something if the string is named. Measured at
 * a 320px viewport with the author name `'a'.repeat(80)`: the `<li>` and the
 * `<p>` stay at 254px — the viewport less `Home.vue`'s `px-4` (32px), the card
 * border (2px) and `CardContent`'s `p-4` (32px) — while the byline `<span>`
 * reaches 600.47px. The same 80 characters as `'W'.repeat(80)` reach 1201.97px,
 * which is why "an 80-character name" is a description and not a measurement.
 * Either way the card's own `overflow-hidden` hides the difference: the
 * document does not scroll (`scrollWidth` 320px), the name is just silently
 * sliced. With the class on the `<li>`, `'a'.repeat(80)` comes back inside the
 * box at 247.78px.
 *
 * Reproduce by removing the class, not by reading the computed style of the
 * shipped one. Established by SSR-rendering `PetListingCard` into a page that
 * loads the stylesheet under the **`resources/css/app.css`** key of
 * `public/build/manifest.json`. Name that key. `resources/js/app.ts` is a
 * second entry that also produces CSS — which is why two `app-*.css` exist with
 * identical mtimes, so resolving by mtime and resolving by the obvious-looking
 * key are one mistake, not two, and picking the newer file more carefully
 * cannot repair it — but the sheet on the `app.ts` branch is sonner's component
 * CSS and carries **zero** Tailwind utilities (`.text-sm`, `.line-clamp-2`,
 * `.break-words` all absent from it). A fixture pointed there renders Times New
 * Roman at 16px in an unrounded box and every width it reports is a fallback
 * figure: nothing errors, the numbers look reasonable, and they describe
 * nothing.
 *
 * `document.fonts.ready` does not gate the faces and must not be treated as if
 * it does. The `@fonts` stylesheet (`bunny('Instrument Sans', { weights: [400,
 * 500, 600] })` in `vite.config.ts`) declares `font-display: swap` on every
 * face, so `ready` resolves while they are still unloaded and the byline gets
 * measured in the fallback family. Await an explicit `document.fonts.load` for
 * each face **and weight** in use — 400 for the body copy, 500 for the
 * `font-medium` byline — before believing any width.
 *
 * Select the byline deliberately too. A bare `ul li` matches
 * `PetCardAttributeIcons`'s `<li>`, which sits earlier in the card and inherits
 * `text-xs` (12px) from its `<ul>`; measuring that returns a width for the
 * wrong element and raises nothing. Disambiguate this `<li>` by the `<p>` it
 * contains, and assert exactly one element matched before reading from it.
 *
 * Two guards, two different failures, neither substituting for the other. That
 * the stylesheet is live: body `margin` 0px, card `border-radius` 12px
 * (`--radius` .75rem), `<li>` `font-size` 14px (`--text-sm` .875rem). That the
 * viewport is the one asked for: `innerWidth` 320px — a fixture with no
 * viewport meta reads CDP's 980px default, and only this assertion catches it.
 * `innerWidth` cannot do the first job: control-measured with the stylesheet
 * link removed it still reads 320px, while the body margin goes to 8px, the
 * radius to 0px and the family to Times New Roman.
 */
const { comment } = defineProps<{ comment: CommentPreview }>();

const { tag } = useLocale();

const writtenAt = computed(() => formatRelative(comment.created_at, tag.value));
</script>

<template>
    <li class="min-w-0 text-sm break-words">
        <span class="font-medium">{{ comment.author?.name ?? 'Someone' }}</span>
        <span class="text-muted-foreground ms-1.5 text-xs">
            {{ writtenAt }}
        </span>

        <p class="text-muted-foreground line-clamp-2">
            {{ comment.content }}
        </p>
    </li>
</template>
