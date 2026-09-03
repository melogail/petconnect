import type { PetBreedOption, PetCategoryOption } from '@/types';

/**
 * The shape the lookup needs: an English `name` plus per-locale `name_<locale>`
 * columns.
 *
 * The pattern index signature is what lets the body read
 * `` entity[`name_${locale}`] `` without a cast. `PetCategoryOption` and
 * `PetBreedOption` both satisfy it, because the only key either has matching
 * the pattern — `name_ar` — is `string | null`. It stays internal so the public
 * signature is still the two option types and a call site cannot hand this an
 * unrelated `{ name }` bag.
 */
type LocalisedNames = { name: string } & Partial<
    Record<`name_${string}`, string | null>
>;

/**
 * A category's or a breed's name in the reader's language.
 *
 * `PetCategoryOptionResource` and `PetBreedOptionResource` ship **both** names
 * on every row ("so the client can pick one per locale without a second round
 * trip"), and until this existed nothing read `name_ar` — so an Arabic reader
 * saw English category and breed names in the filter tree and in the pet form's
 * two taxonomy selects. The legacy app had the same gap; this is a bug fix, not
 * a port of one.
 *
 * The lookup is general — `name_${locale}` for whatever `locale.current` says,
 * falling back to `name` — so nothing here hardcodes `'ar'`.
 * .ai/rules/lang.md forbids hardcoding `=== 'ar'` because
 * `petconnect.locales.rtl` is the one authority for *direction*; a suffix
 * lookup asserts neither a direction nor a language list, and a third locale
 * means adding a `name_xx` column and nothing on this side of the wire.
 *
 * The fallback covers both misses that occur in practice: a locale with no
 * column at all (there is no `name_en` — English *is* `name`), and a row whose
 * translation was never filled in, which arrives as `null` rather than absent.
 * An empty string counts as a miss too, since a blank label is worse than an
 * English one.
 *
 * Pure, and it takes the locale as an argument rather than calling
 * `useLocale()` itself, so it is usable outside a component. Call sites read
 * `locale.current` — the *language*, a different field from `locale.direction`.
 */
export function taxonomyName(
    entity: PetCategoryOption | PetBreedOption,
    locale: string,
): string {
    const names: LocalisedNames = entity;
    const localised = names[`name_${locale}`];

    return typeof localised === 'string' && localised !== ''
        ? localised
        : entity.name;
}
