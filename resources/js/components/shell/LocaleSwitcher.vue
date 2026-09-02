<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { update as updateLocale } from '@/routes/locale';

/**
 * The language pill in the header.
 *
 * The options come from `locale.supported`, which `HandleInertiaRequests`
 * mirrors from `config('petconnect.locales.supported')` — the one whitelist.
 * Nothing here compares against `'ar'`: direction is `locale.direction`'s job
 * (.ai/rules/lang.md), and the switch itself only ever posts a code the server
 * already told us it accepts.
 *
 * `locale.update` re-sends the `translations` once-prop keyed by the new
 * locale and `initializeLocaleDirection()` rewrites `<html lang|dir>`, so the
 * Inertia visit is enough — no reload.
 *
 * The selected pill is `bg-primary-600`, not `-500`: white on violet-500
 * measures 4.23:1 and fails AA, white on violet-600 measures 5.70:1. `app.css`
 * says as much next to the step — violet-500 does not carry readable text.
 */
const { locale } = useLocale();
const { t } = useTranslations();

/**
 * The pill shows a short glyph rather than the language's full name, and the
 * glyph is translated, not derived: `ar` reads `ع`, which uppercasing the code
 * would never produce. A locale with no short key falls back to its full name
 * from `locales.*`, which every supported locale has.
 */
const shortLabelKeys: Record<string, string> = {
    en: 'nav.english',
    ar: 'nav.arabic',
};

const options = computed(() =>
    locale.value.supported.map((code) => ({
        code,
        short: t(shortLabelKeys[code] ?? `locales.${code}`),
        label: t(`locales.${code}`),
    })),
);

function select(code: string): void {
    if (code === locale.value.current) {
        return;
    }

    router.post(updateLocale.url(), { locale: code }, { preserveScroll: true });
}
</script>

<template>
    <div
        class="border-border inline-flex items-center rounded-full border p-0.5"
        role="group"
        :aria-label="t('nav.language')"
    >
        <button
            v-for="option in options"
            :key="option.code"
            type="button"
            :lang="option.code"
            :title="option.label"
            :aria-label="option.label"
            :aria-pressed="option.code === locale.current"
            class="rounded-full px-2.5 py-1 text-xs font-semibold transition-colors"
            :class="
                option.code === locale.current
                    ? 'bg-primary-600 text-white'
                    : 'text-muted-foreground hover:text-primary-600 dark:hover:text-primary-400'
            "
            @click="select(option.code)"
        >
            {{ option.short }}
        </button>
    </div>
</template>
