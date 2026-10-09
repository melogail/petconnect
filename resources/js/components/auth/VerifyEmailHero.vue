<script setup lang="ts">
import { Mail } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The flat lavender band across the top of the verification card.
 *
 * It used to be a violet→fuchsia gradient with two soft decorative circles and
 * pinned `text-white`. The "Aubergine & Lavender" restyle retired all of that:
 * violet is the one accent (no fuchsia, no gradients), and a hero panel is a
 * flat `bg-secondary` surface, so the circles went with the gradient — a flat
 * panel has nothing for them to soften. The band is symmetric now, so the
 * logical-offset argument they carried no longer applies.
 *
 * Painted on tokens, the band re-tones with the scheme instead of staying
 * fixed: `bg-secondary` is `#EEEAFB` light / `#261D45` dark. The one solid
 * violet on this surface is the icon disc (`bg-primary text-primary-foreground`,
 * white on `#5A3FE0` 6.52:1 light, `#15102A` on `#A78BFF` 6.83:1 dark). The
 * heading inherits `text-secondary-foreground` (`#4A32C4` on `#EEEAFB`
 * 6.99:1; `#CDBDFF` on `#261D45` 9.17:1). The body line is
 * `text-soft-foreground` rather than `text-secondary-foreground/80`: both clear
 * AA on the lavender panel, but at 80 % alpha the violet lands at 4.59:1 —
 * a hair over the 4.5:1 floor — while soft text measures 8.21:1 light
 * (`#4A4059` on `#EEEAFB`) and 9.21:1 dark (`#CBC3DC` on `#261D45`). Figures
 * from the hex values in `resources/css/app.css` via the WCAG relative-
 * luminance formula.
 */
const { t } = useTranslations();
</script>

<template>
    <div
        class="bg-secondary bg-gradient-wash text-secondary-foreground relative overflow-hidden px-6 pt-8 pb-10"
    >
        <div class="relative flex flex-col items-center gap-4 text-center">
            <div
                class="bg-primary bg-gradient-brand text-primary-foreground flex size-16 items-center justify-center rounded-[20px]"
            >
                <Mail class="size-8" stroke-width="1.75" />
            </div>
            <div class="space-y-2">
                <h1
                    class="font-serif text-[26px] leading-[1.15] font-medium tracking-[-0.01em]"
                >
                    {{ t('auth.verify_email_heading') }}
                </h1>
                <p class="text-soft-foreground max-w-sm text-sm">
                    {{ t('auth.verify_email_description') }}
                </p>
            </div>
        </div>
    </div>
</template>
