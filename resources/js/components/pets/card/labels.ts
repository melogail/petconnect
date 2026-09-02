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
