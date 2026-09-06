import type { useTranslations } from '@/composables/useTranslations';

/**
 * Accessible-name helpers for the card's counted controls.
 *
 * The *visible* label of a like or comment control is the bare number — that is
 * what fits a 320px card. The *accessible* name has to say what the number
 * counts, and "1 comments" is exactly the kind of thing that only ever reaches
 * a screen-reader user, so the plural is decided once here rather than in three
 * template ternaries.
 *
 * English-only, matching the rest of the feed subtree. A single cross-boundary
 * i18n pass is scheduled after this phase and owns `lang/*.json`; do not add
 * `useTranslations` here ahead of it.
 */
export function countLabel(count: number, singular: string): string {
    return `${count} ${count === 1 ? singular : `${singular}s`}`;
}

type Translate = ReturnType<typeof useTranslations>['t'];

/**
 * "7 years" / "1 year", from the varchar `age` column.
 *
 * Shared by the card's meta line and its hover bar so the two cannot disagree
 * about the plural. Takes the caller's `t` rather than calling
 * `useTranslations()` itself: this file is a plain module, not a component,
 * and the composable reads `usePage()`. `pets.age_year` / `pets.age_years`
 * exist in both catalogues.
 */
export function ageLabel(t: Translate, age: string): string {
    return t(Number(age) === 1 ? 'pets.age_year' : 'pets.age_years', {
        count: age,
    });
}
