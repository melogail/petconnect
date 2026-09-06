<script setup lang="ts">
import { Mail } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The gradient band across the top of the verification card.
 *
 * The two soft circles are decoration and carry `pointer-events-none`, so they
 * never intercept a click meant for the card. They are positioned with logical
 * offsets (`-end-8`, `start-8`), which mirrors them under `dir="rtl"` — the
 * band is asymmetric, so physical `-right`/`left` would have put the large
 * circle on the same side in both directions and left the composition
 * lopsided against the text.
 *
 * Everything here is painted on the violet→fuchsia gradient rather than on a
 * theme token, so it reads identically in both schemes; that is the point of a
 * hero band, and it is why `text-white` is pinned rather than inherited.
 */
const { t } = useTranslations();
</script>

<template>
    <div
        class="relative overflow-hidden bg-linear-to-br from-violet-500 via-violet-600 to-fuchsia-500 px-6 pt-8 pb-10 text-white"
    >
        <div
            class="pointer-events-none absolute -end-8 -top-10 size-40 rounded-full bg-white/10"
        />
        <div
            class="pointer-events-none absolute start-8 -bottom-12 size-32 rounded-full bg-fuchsia-300/20"
        />

        <div class="relative flex flex-col items-center gap-4 text-center">
            <div
                class="flex size-16 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur-sm"
            >
                <Mail class="size-8" stroke-width="1.75" />
            </div>
            <div class="space-y-2">
                <h1 class="text-2xl font-bold tracking-tight">
                    {{ t('auth.verify_email_heading') }}
                </h1>
                <p class="max-w-sm text-sm text-violet-50/95">
                    {{ t('auth.verify_email_description') }}
                </p>
            </div>
        </div>
    </div>
</template>
