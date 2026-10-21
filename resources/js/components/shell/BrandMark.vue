<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

/**
 * The wordmark in the public header: the brand mark and the brand name beside
 * it.
 *
 * It is the only wordmark now. `AppLogo` was the member-shell equivalent and
 * painted itself with `--sidebar-primary`; it went with the sidebar shell
 * (2026-09-06).
 *
 * The mark is `AppLogoIcon` — the SniffPal chat bubble carrying the pet face
 * with the heart nose, the same drawing as the favicon, the app icon and the
 * mobile app (`public/brand/sniffpal-mark.svg`). Until 2026-10-11 the header
 * drew a 30px `rounded-[10px]` square of `--primary` with a 9px dot in its
 * place, the "Aubergine & Lavender" guide's §5.3 stand-in from before the
 * mark existed; the auth layouts already rendered the bubble, so the site
 * showed two marks. It is the bubble everywhere now, 30px here so it keeps
 * the header's cluster height. The bubble is full colour and pins its own
 * violet (see `AppLogoIcon`), so no text colour here tones it.
 *
 * The wordmark is Newsreader (`font-serif`) 21/500 with the heading tracking,
 * which under `dir="rtl"` resolves to Readex Pro through `app.css`.
 */
const { t } = useTranslations();
</script>

<template>
    <Link :href="home()" class="flex items-center gap-2.5">
        <AppLogoIcon class="size-[30px] shrink-0" aria-hidden="true" />
        <span
            class="text-foreground font-serif text-[21px] font-medium tracking-[-0.015em] max-sm:sr-only"
        >
            {{ t('nav.brand') }}
        </span>
    </Link>
</template>
