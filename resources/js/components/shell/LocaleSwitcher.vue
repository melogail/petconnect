<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { nameContaining } from '@/components/shell/labels';
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
 * The selected segment is `bg-primary text-primary-foreground`, the one solid
 * violet the style guide allows per surface (§1, §5.3): white on `--primary`
 * #5A3FE0 measures 6.2:1 in light, and #15102A on #A78BFF 9.6:1 in dark. It
 * used to be pinned to `bg-primary-600` because white on violet-500 measured
 * 4.23:1 and failed AA; the token now carries that choice, so the step is not
 * repeated here. The frame is the header's 14px control radius with 10px
 * segments inside it, not a pill — it is a segmented control, and the
 * `rounded-full` treatment is reserved for status and count pills.
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

/**
 * `label` is the pill's accessible name and `short` is what it puts on screen,
 * so the pair has to satisfy WCAG 2.5.3 — and it did not, in the locale where
 * it is easiest to miss.
 *
 * The glyph is translated independently of the language it names, so whether
 * the name contains it is a **per-locale** property with four combinations, not
 * one. Read out of Chrome's accessibility tree over CDP against an isolated
 * build, 2026-09-06:
 *
 * | reading locale | pill | visible | name (before) | contained |
 * | -------------- | ---- | ------- | ------------- | --------- |
 * | `en`           | `en` | `EN`    | `English`     | yes, case-insensitively |
 * | `en`           | `ar` | `AR`    | `العربية`      | **no** |
 * | `ar`           | `en` | `EN`    | `English`     | yes |
 * | `ar`           | `ar` | `ع`     | `العربية`      | yes — `ع` is the third codepoint of it |
 *
 * So exactly one cell failed, and it is the one an English-reading auditor sees
 * on the first screen: the Arabic pill reads "AR" and announced "العربية". A
 * check written once, in either locale, passes three times out of four and
 * reports the control as fine.
 *
 * `nameContaining` prefixes the glyph only when the name does not already carry
 * it, so the three passing cells are byte-identical to before and the `en`/`ar`
 * asymmetry disappears without a new catalogue key.
 */
const options = computed(() =>
    locale.value.supported.map((code) => {
        const short = t(shortLabelKeys[code] ?? `locales.${code}`);
        const label = t(`locales.${code}`);

        return { code, short, label, name: nameContaining(short, label) };
    }),
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
        class="border-border bg-card inline-flex items-center rounded-[14px] border p-0.5"
        role="group"
        :aria-label="t('nav.language')"
    >
        <button
            v-for="option in options"
            :key="option.code"
            type="button"
            :lang="option.code"
            :title="option.label"
            :aria-label="option.name"
            :aria-pressed="option.code === locale.current"
            class="focus-visible:ring-ring focus-visible:ring-offset-background rounded-[10px] px-2.5 py-1 text-xs font-semibold transition-[background-color,border-color,box-shadow,transform] duration-200 ease-out outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
            :class="
                option.code === locale.current
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
            "
            @click="select(option.code)"
        >
            {{ option.short }}
        </button>
    </div>
</template>
