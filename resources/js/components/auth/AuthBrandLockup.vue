<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

/**
 * The brand lockup above the verification card: violet rounded square, white
 * dot, brand name, linking home.
 *
 * ## Why this is not `shell/BrandMark.vue`
 *
 * It is the same mark, and reusing it was the first thing tried. It differs in
 * two ways, not one, and both land on inner elements a call-site class cannot
 * reach:
 *
 * 1. `BrandMark` hides its wordmark below `sm` (`max-sm:sr-only`,
 *    `BrandMark.vue:25`), which is right where it lives — the public header cluster, whose 320px overflow
 *    phase 1 fixed by shedding exactly that text — and wrong here, where the
 *    lockup is centred and alone on the screen with a whole viewport to
 *    itself. That class sits on an inner `<span>`.
 * 2. The mark here carries `shadow-sm shadow-violet-500/30`, which
 *    `BrandMark.vue:20` does not. That has to land on the inner `<span>` that
 *    *is* the violet square, not on the `<Link>` root a passed class binds to.
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
        <span
            class="bg-primary-500 flex size-10 items-center justify-center rounded-2xl shadow-sm shadow-violet-500/30"
            aria-hidden="true"
        >
            <span class="size-6 rounded-full bg-white" />
        </span>
        <span class="text-foreground text-xl font-bold">
            {{ t('nav.brand') }}
        </span>
    </Link>
</template>
