import type { useTranslations } from '@/composables/useTranslations';
import { taxonomyName } from '@/lib/taxonomy';
import type { PetBreedOption, PetCategoryOption } from '@/types';

type Translate = ReturnType<typeof useTranslations>['t'];

/** The slice of `PetCard` / `PetDetail` a title is built from. */
type Titled = {
    name: string | null;
    quantity: number;
    breed?: PetBreedOption | null;
    category?: PetCategoryOption;
};

/**
 * What to call a listing: its name, or a litter title when it has none.
 *
 * `pets.name` is nullable since a listing can offer a whole litter for
 * adoption, and a litter has no single name. Every place that used to read
 * `pet.name` — the heading, the `<title>`, the accessible names on the like,
 * message and share controls — reads this instead, so a litter is announced
 * as "Litter of 4 Golden Retriever" rather than as an empty string.
 *
 * Pure, and it takes `t` and the locale as arguments rather than calling the
 * composables itself, so it is usable from a plain module as well as a
 * component. The kind goes through `taxonomyName`, never `.name`, for the
 * same reason the cards do.
 */
export function petTitle(pet: Titled, t: Translate, locale: string): string {
    if (pet.name !== null && pet.name !== '') {
        return pet.name;
    }

    const taxon = pet.breed ?? pet.category;

    return t('pets.litter_title', {
        count: String(pet.quantity),
        kind: taxon ? taxonomyName(taxon, locale) : t('pets.unknown_pet'),
    });
}
