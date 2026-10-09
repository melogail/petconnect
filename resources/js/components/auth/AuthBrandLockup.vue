<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

/**
 * The brand lockup above the verification card: the SniffPal mark
 * (`AppLogoIcon`), brand name, linking home.
 *
 * ## Why this is not `shell/BrandMark.vue`
 *
 * It is the same mark, and reusing it was the first thing tried. When this
 * file was written it differed in two ways, both landing on inner elements a
 * call-site class cannot reach:
 *
 * 1. `BrandMark` hides its wordmark below `sm` (`max-sm:sr-only` on its
 *    wordmark `<span>`), which is right where it lives — the public header cluster, whose 320px overflow
 *    phase 1 fixed by shedding exactly that text — and wrong here, where the
 *    lockup is centred and alone on the screen with a whole viewport to
 *    itself. That class sits on an inner `<span>`. This difference still
 *    holds.
 * 2. The mark here used to carry `drop-shadow-sm drop-shadow-violet-500/30`,
 *    which `BrandMark`'s did not. The "Aubergine & Lavender" restyle removed
 *    it: violet is the one accent and shadows are neutral — they lift surfaces
 *    (cards, popovers, the primary button's `shadow-cta`), never glyphs — so a
 *    chromatic drop-shadow on the mark had no place left. The mark now sits
 *    flat, as it does in the header.
 *
 * The third difference — this lockup centres itself with `justify-center` — is
 * the only one a call-site class *could* carry, since it sits on the root.
 *
 * With the shadow gone, a single visibility prop on `BrandMark` would be
 * enough to replace this file. It has not been done: a prop added to
 * `components/shell/**` for one auth screen is still a wider interface than
 * ten duplicated lines of markup cost, and the shell is not this screen's to
 * widen. The duplication stands — deliberate, and recorded rather than
 * silent. If the two are ever unified, do it from the shell side with that
 * one prop; do not "fix" it from here.
 *
 * The wordmark is the guide's brand-wordmark step: Newsreader (`font-serif`)
 * 21px / 500 / −0.015em, the same as `BrandMark`'s should be.
 */
const { t } = useTranslations();
</script>

<template>
    <Link :href="home()" class="flex items-center justify-center gap-2.5">
        <AppLogoIcon class="size-10 shrink-0" aria-hidden="true" />
        <span
            class="text-foreground font-serif text-[21px] font-medium tracking-[-0.015em]"
        >
            {{ t('nav.brand') }}
        </span>
    </Link>
</template>
