<script setup lang="ts">
import { CircleCheck } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * Confirmation that a fresh verification link just went out.
 *
 * `role="status"` rather than `role="alert"`: it appears after the reader's own
 * click on "resend", so it is a polite confirmation, not an interruption, and
 * an assertive live region would cut across whatever a screen reader was
 * already saying about the button.
 *
 * The glyph is `CircleCheck` — check enclosed by the circle — because that is
 * what the legacy screen's `CheckCircle2` resolves to. Established by grepping
 * both installed packages, not from memory: `@lucide/vue/dist/lucide-vue.d.ts`
 * and `petconnect-old/node_modules/lucide-vue-next/dist/lucide-vue-next.d.ts`
 * both export `CircleCheck as CheckCircle2` and `CircleCheckBig as
 * CheckCircle`. `CircleCheckBig` is a visibly different mark (the check
 * overflows the circle) and is what `CheckCircle`, not `CheckCircle2`, would
 * have given.
 *
 * Emerald is a literal palette rather than a token because success is not a
 * colour this theme names — there is no `--success`. Contrast computed from
 * Tailwind v4's `oklch()` stops (`node_modules/tailwindcss/theme.css`)
 * converted to linear sRGB: `emerald-800` on `emerald-50` **7.19:1** light,
 * and in dark the panel is `emerald-950/40` over `--card` `#1D283A`, where
 * `emerald-200` measures **13.96:1** against the darkest thing behind it,
 * `--background` `#0F1729`. Both clear the 4.5:1 AA floor.
 */
const { t } = useTranslations();
</script>

<template>
    <div
        class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-start dark:border-emerald-900/50 dark:bg-emerald-950/40"
        role="status"
    >
        <CircleCheck
            class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
        />
        <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">
            {{ t('auth.verification_link_sent') }}
        </p>
    </div>
</template>
