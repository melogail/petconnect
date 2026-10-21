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
 * "7 years" / "1 year" / "3 months", from the derived `age` in years.
 *
 * Under a year it is shown in months, since "0.25 years" is not how anybody
 * describes a puppy; a whole number of years is shown as such and anything
 * in between is rounded to one decimal. Shared by the card's meta line, its
 * hover bar and the detail header so the three cannot disagree about the
 * plural. Takes the caller's `t` rather than calling `useTranslations()`
 * itself: this file is a plain module, not a component, and the composable
 * reads `usePage()`. All four keys exist in both catalogues.
 */
export function ageLabel(t: Translate, age: number): string {
    if (age < 1) {
        const months = Math.max(1, Math.round(age * 12));

        return t(months === 1 ? 'pets.age_month' : 'pets.age_months', {
            count: String(months),
        });
    }

    const years = Math.round(age * 10) / 10;

    return t(years === 1 ? 'pets.age_year' : 'pets.age_years', {
        count: String(years),
    });
}

/**
 * "2 males, 1 female", for a litter. Null for a single animal, whose sex is
 * carried by `gender` and rendered by the caller.
 */
export function litterLabel(
    t: Translate,
    quantity: number,
    maleCount: number | null,
    femaleCount: number | null,
): string | null {
    if (quantity <= 1) {
        return null;
    }

    return t('pets.litter_sexes', {
        male: String(maleCount ?? 0),
        female: String(femaleCount ?? 0),
    });
}
