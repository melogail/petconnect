import { router, usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';
import type {
    TranslationCatalogue,
    TranslationReplacements,
} from '@/types/translations';

export type UseTranslationsReturn = {
    /** The whole catalogue, for the rare caller that needs to look around it. */
    translations: ComputedRef<TranslationCatalogue>;
    /** One string, by the key `__()` takes, with `:name` / `{name}` filled in. */
    t: (key: string, replace?: TranslationReplacements) => string;
    /** Ask the server for the catalogue again. */
    refresh: () => void;
};

/**
 * Fill `:name` and `{name}` placeholders, longest key first.
 *
 * Both spellings, because both are in the catalogue: the backend's own
 * `__()` strings use Laravel's `:name`, and a handful of ported legacy strings
 * use `{name}`.
 *
 * Longest first is what keeps `:count` from eating the front of `:count_other`.
 * Laravel's own MessageSelector sorts for the same reason; a plain
 * `Object.entries()` order is insertion order and would leave `_other` dangling
 * after the substituted number.
 */
function interpolate(
    template: string,
    replace: TranslationReplacements,
): string {
    return Object.entries(replace)
        .sort(([a], [b]) => b.length - a.length)
        .reduce(
            (result, [key, value]) =>
                result
                    .replaceAll(`:${key}`, String(value))
                    .replaceAll(`{${key}}`, String(value)),
            template,
        );
}

/**
 * The client half of the locale layer: `t(key)`.
 *
 * ## The contract
 *
 * `t(key, replace?)` looks `key` up in the `translations` shared prop — the
 * flat `lang/{locale}.json` map, keys exactly as `__()` takes them — and
 * returns the value with its placeholders filled. **A key that is not in the
 * catalogue returns the key itself**, unchanged and uninterpolated. That is
 * deliberate: a missing translation shows up in the UI as `notifications.foo`,
 * which is a bug report, rather than as an empty box, which is not.
 *
 * Both key styles the catalogue mixes work, because this does not care which
 * it is handed: `t('nav.brand')` and `t('Notifications marked as read.')` are
 * the same lookup. .ai/rules/lang.md is explicit that the two styles coexist on
 * purpose — do not normalise either into the other on this side of the wire.
 *
 * ## `?? {}` is load bearing
 *
 * `translations` is an `Inertia::once()` prop keyed `translations.{locale}`
 * (HandleInertiaRequests::shareOnce), so it rides the initial document and is
 * **absent from the props of every Inertia visit afterwards** — the client
 * holds the copy it was sent and restores it. `?? {}` covers the window in
 * which it is genuinely not there yet, and turns "no catalogue" into "every key
 * falls back to itself" rather than a `TypeError` that blanks the page. The
 * prop is typed optional in `global.d.ts` for the same reason.
 *
 * ## This does not decide the language or the direction
 *
 * `useLocale()` does, from the `locale` shared prop, and that prop is the
 * authority — `locale.current` for `<html lang>`, `locale.direction` (built
 * from `petconnect.locales.rtl`) for `<html dir>`. Do not infer either from
 * what happens to be in the catalogue: the catalogue is remembered across
 * visits and the locale prop is re-sent on every one, so a derived answer would
 * be the stale one exactly when it mattered. `initializeLocaleDirection()` in
 * `lib/localeDirection.ts` already writes both onto the document.
 *
 * ## `refresh()`
 *
 * A language switch re-sends the catalogue on its own — the once key carries
 * the locale, so the server offers a key the client is not holding and resolves
 * it. `refresh()` is the manual escape hatch for anything that changes the
 * catalogue without changing the locale, and it is a partial reload so it costs
 * one prop rather than a page.
 */
export function useTranslations(): UseTranslationsReturn {
    const page = usePage();

    const translations = computed<TranslationCatalogue>(
        () => page.props.translations ?? {},
    );

    function t(key: string, replace: TranslationReplacements = {}): string {
        return interpolate(translations.value[key] ?? key, replace);
    }

    function refresh(): void {
        router.reload({ only: ['translations'] });
    }

    return { translations, t, refresh };
}
