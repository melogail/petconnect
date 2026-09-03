import type { HomeFeedFilters, PetCategoryOption } from '@/types';

/**
 * Which breeds are picked, keyed by the category they belong to.
 *
 * This one map is the whole taxonomy draft. There is deliberately no separate
 * "selected categories" list, because a category is not an independent choice:
 * on the wire it is shorthand for *all* of its breeds, and the tree has to be
 * able to tell "every breed of Dogs" from "eleven of the twenty" to decide
 * which of the two query keys to send. Keeping one map and deriving the rest is
 * what makes those two states impossible to hold at once.
 *
 * **Invariant, relied on by `categoryState`:** an entry for a category contains
 * only ids of that category's own breeds, and no duplicates. Every writer below
 * upholds it, and the only outside writer — the breed list — is handed exactly
 * that category's breeds as its options.
 *
 * An entry is deleted rather than set to `[]`, so `Object.keys()` is the list of
 * categories with a selection. `AnimalTypeFilter`'s expand-on-selection watcher
 * reads it that way, so a stray `[]` entry opens a panel whose checkbox then
 * reads unticked. Write through `setCategoryBreeds`, which deletes on empty;
 * `hydrateSelection` used to assign directly and produced exactly that.
 *
 * **Limitation, and it is a real one:** a category with no breeds cannot be
 * represented here at all. Its entry could only be `[]`, which is both "all of
 * its breeds" and "none of them", so `categoryState` reports `false`,
 * `CategoryFilterRow` disables the control, and `hydrateSelection` drops a
 * `?category_ids=N` naming it. The trigger's counter reads the server's
 * `filters`, not this map, so such an id still counts towards the badge while
 * nothing in the tree can show or clear it. That is a limit of the two-key
 * query, not of any function below, and it is unreachable with the seeded
 * taxonomy: `database/data/categories.json` + `breeds.json` give all seven
 * categories breeds, counted 2026-09-03 as dogs 20, cats 18, birds 10, fish 10,
 * rabbits 8, small-pets 8, reptiles 8.
 */
export type CategorySelection = Record<number, number[]>;

/** The two taxonomy keys the feed's query string takes. */
export type TaxonomyQuery = {
    category_ids: number[] | null;
    breed_ids: number[] | null;
};

/** A checkbox state as `ui/checkbox` models it. */
export type CheckboxState = boolean | 'indeterminate';

function breedIdsOf(category: PetCategoryOption): number[] {
    return (category.breeds ?? []).map((breed) => breed.id);
}

export function selectedBreedIds(
    selection: CategorySelection,
    categoryId: number,
): number[] {
    return selection[categoryId] ?? [];
}

/**
 * The parent checkbox's tri-state: all breeds picked, some, or none.
 *
 * A category with no breeds at all is `false` and stays there — it can never be
 * selected, because the only way to select one is to select all of its breeds
 * and there are none. See `CategoryFilterRow`, which disables the control
 * rather than leaving a checkbox that does nothing.
 */
export function categoryState(
    selected: number[],
    category: PetCategoryOption,
): CheckboxState {
    const total = category.breeds?.length ?? 0;

    if (total === 0 || selected.length === 0) {
        return false;
    }

    return selected.length === total ? true : 'indeterminate';
}

/**
 * Replace one category's breeds, dropping the entry when nothing is left.
 *
 * Returns a new map. The tree re-emits its model on every edit, and an identity
 * change is what `AnimalTypeFilter`'s expand-on-selection watcher fires on.
 */
export function setCategoryBreeds(
    selection: CategorySelection,
    categoryId: number,
    breedIds: number[],
): CategorySelection {
    const next = { ...selection };

    if (breedIds.length === 0) {
        delete next[categoryId];
    } else {
        next[categoryId] = breedIds;
    }

    return next;
}

/**
 * The parent checkbox, with the standard nested-tree semantics: checked or
 * indeterminate clears the category, unchecked selects every breed in it.
 *
 * Note that indeterminate clears rather than completing the set — that is what
 * `ui/checkbox` would do on its own (reka's `CheckboxRoot` moves indeterminate
 * to `trueValue`), which is why the call site ignores the value reka emits and
 * calls this instead.
 */
export function toggleCategory(
    selection: CategorySelection,
    category: PetCategoryOption,
): CategorySelection {
    const isCleared =
        categoryState(selectedBreedIds(selection, category.id), category) ===
        false;

    return setCategoryBreeds(
        selection,
        category.id,
        isCleared ? breedIdsOf(category) : [],
    );
}

/**
 * Rebuild the draft from the normalised bag the server echoed back.
 *
 * The inverse of `taxonomyQuery`, and it has to be: a `category_id` in the URL
 * re-selects every one of that category's breeds, so the checkbox comes back
 * fully checked rather than empty, and matched `breed_ids` come back
 * individually. Ids that name nothing in the current category list are dropped
 * — the alternative is a draft that re-sends an id the visitor cannot see or
 * clear.
 *
 * Breeds win over the category id when a hand-written URL carries both, because
 * the narrower of the two is the safer thing to show.
 *
 * Every write goes through `setCategoryBreeds` rather than assigning, so the
 * "deleted, never `[]`" invariant holds for the one input that can produce an
 * empty pick: a breedless category named in `category_ids`, whose
 * `breedIdsOf()` is `[]`. Assigning that used to leave a phantom entry —
 * `Object.keys()` listed the category, so its panel sprang open, while
 * `categoryState` reported `false` so the box read unticked and `taxonomyQuery`
 * dropped the id on the next Apply. See the type's docblock for what remains
 * true of a breedless category even now.
 */
export function hydrateSelection(
    categories: PetCategoryOption[],
    filters: HomeFeedFilters,
): CategorySelection {
    const wantedBreedIds = new Set(filters.breed_ids);
    const wantedCategoryIds = new Set(filters.category_ids);

    return categories.reduce<CategorySelection>((selection, category) => {
        const breedIds = breedIdsOf(category);
        const matched = breedIds.filter((id) => wantedBreedIds.has(id));

        if (matched.length > 0) {
            return setCategoryBreeds(selection, category.id, matched);
        }

        return setCategoryBreeds(
            selection,
            category.id,
            wantedCategoryIds.has(category.id) ? breedIds : [],
        );
    }, {});
}

/**
 * Split the draft into the two query keys.
 *
 * They are alternatives per category, not two independent lists: a category
 * whose breeds are *all* picked travels as its own id, so the feed keeps
 * matching it as the taxonomy grows and the URL stays short; a partial pick
 * travels as the breed ids themselves. Sending both for one category would
 * widen the filter back to the whole category, since the backend ORs them.
 *
 * `null` rather than `[]` for an empty side because `null` is the honest value
 * for "unset", and it is what `TaxonomyQuery` is typed as.
 *
 * It is **not** the difference between clearing the family and leaving it: in
 * `mergeQuery`, `queryParams()` calls `clearParamFamily(params, key)` for every
 * key *before* the null check (`resources/js/wayfinder/index.ts`, generated —
 * find it by name, not by line), so a present key clears its family whatever
 * its value. Measured 2026-09-03 against
 * `?category_ids[]=3&category_ids[]=4&breed_ids[]=9&latitude=30`: `null`, `[]`
 * and `undefined` all produce `?latitude=30`, byte for byte. Only **omitting**
 * the key leaves the family alone — which is a different tool, and the one
 * `PetFilterSheet.apply()` uses while `categories` is still deferred.
 *
 * Distinct from .ai/rules/js.md's FormData rule, which is about `[]` vanishing
 * from a multipart body. That rule is real and this is not an instance of it:
 * nothing here is a form submission.
 */
export function taxonomyQuery(
    categories: PetCategoryOption[],
    selection: CategorySelection,
): TaxonomyQuery {
    const categoryIds: number[] = [];
    const breedIds: number[] = [];

    for (const category of categories) {
        const selected = selectedBreedIds(selection, category.id);

        if (selected.length === 0) {
            continue;
        }

        if (categoryState(selected, category) === true) {
            categoryIds.push(category.id);
        } else {
            breedIds.push(...selected);
        }
    }

    return {
        category_ids: categoryIds.length > 0 ? categoryIds : null,
        breed_ids: breedIds.length > 0 ? breedIds : null,
    };
}
