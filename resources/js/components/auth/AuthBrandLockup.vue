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
 * It is the same mark, and reusing it was the first thing tried. It differs in
 * two ways, not one, and both land on inner elements a call-site class cannot
 * reach:
 *
 * 1. `BrandMark` hides its wordmark below `sm` (`max-sm:sr-only` on its
 *    wordmark `<span>`), which is right where it lives — the public header cluster, whose 320px overflow
 *    phase 1 fixed by shedding exactly that text — and wrong here, where the
 *    lockup is centred and alone on the screen with a whole viewport to
 *    itself. That class sits on an inner `<span>`.
 * 2. The mark here carries `drop-shadow-sm drop-shadow-violet-500/30`, which
 *    `BrandMark`'s does not. That has to land on the inner `AppLogoIcon`, not
 *    on the `<Link>` root a passed class binds to. It is a `drop-shadow`
 *    filter rather than a `box-shadow` because the mark is a speech bubble,
 *    not a box: a box shadow would trace the square around it.
 *
 * The third difference — this lockup centres itself with `justify-center` — is
 * the only one a call-site class *could* carry, since it sits on the root.
 *
 * So a visibility prop alone would not let `BrandMark` replace this file;
 * unifying needs two props. A two-prop `BrandMark` serving one public header
 * and one auth screen is a wider interface than ten duplicated lines of markup
 * cost, so the duplication stands — deliberate, and recorded rather than
 * silent. Do not "fix" it by widening `components/shell/**`.
 */
const { t } = useTranslations();
</script>

<template>
    <Link :href="home()" class="flex items-center justify-center gap-2.5">
        <AppLogoIcon
            class="size-10 shrink-0 drop-shadow-sm drop-shadow-violet-500/30"
            aria-hidden="true"
        />
        <span class="text-foreground text-xl font-bold">
            {{ t('nav.brand') }}
        </span>
    </Link>
</template>
