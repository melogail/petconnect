<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
 * The mark is the "Aubergine & Lavender" one (`.claude/PETCONNECT-STYLE-GUIDE.md`
 * §5.3): a 30px `rounded-[10px]` square of `--primary` carrying a 9px dot in
 * `--primary-foreground`. It is drawn with tokens rather than pinned to a hex
 * so it re-tones with the scheme — violet with a white dot in light, lavender
 * with an aubergine dot in dark. The previous `AppLogoIcon` chat-bubble mark
 * (a hardcoded violet→indigo gradient) is retired here; the auth layouts
 * still render it. The square itself carries `bg-gradient-brand` — the tonal
 * primary-600→700 (dark 300→400) gradient from app.css — over the `bg-primary`
 * fallback, the same surface as the solid button.
 *
 * The wordmark is Newsreader (`font-serif`) 21/500 with the heading tracking,
 * which under `dir="rtl"` resolves to Readex Pro through `app.css`.
 */
const { t } = useTranslations();
</script>

<template>
    <Link :href="home()" class="flex items-center gap-2.5">
        <span
            class="bg-primary bg-gradient-brand flex size-[30px] shrink-0 items-center justify-center rounded-[10px]"
            aria-hidden="true"
        >
            <span class="bg-primary-foreground size-[9px] rounded-full" />
        </span>
        <span
            class="text-foreground font-serif text-[21px] font-medium tracking-[-0.015em] max-sm:sr-only"
        >
            {{ t('nav.brand') }}
        </span>
    </Link>
</template>
