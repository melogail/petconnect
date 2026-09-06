<script setup lang="ts">
import { Deferred, router } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AgeRangeSlider from '@/components/pets/filter/AgeRangeSlider.vue';
import AnimalTypeFilter from '@/components/pets/filter/AnimalTypeFilter.vue';
import FilterCheckboxList from '@/components/pets/filter/FilterCheckboxList.vue';
import type { FilterOption } from '@/components/pets/filter/FilterCheckboxList.vue';
import FilterCheckboxRow from '@/components/pets/filter/FilterCheckboxRow.vue';
import FilterSection from '@/components/pets/filter/FilterSection.vue';
import {
    type CategorySelection,
    hydrateSelection,
    taxonomyQuery,
    type TaxonomyQuery,
} from '@/components/pets/filter/selection';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';
import type {
    HomeFeedBounds,
    HomeFeedFilters,
    PetCategoryOption,
    PetListingType,
    SelectOption,
} from '@/types';
import type { QueryParams } from '@/wayfinder';

/**
 * The feed's filter sheet: animal type, age, adoption type, vaccination.
 *
 * ## Filters live in the query string
 *
 * `home` echoes the normalised bag back as `filters`, so the sheet is seeded
 * from the server's answer rather than from anything it remembers, and a shared
 * URL reproduces the same feed. Applying is an ordinary GET visit.
 *
 * Every key is written through `mergeQuery`, which starts from the current
 * query string and clears only the families being set. That is what keeps a
 * nearby search (`latitude` / `longitude` / `radius`, which this component
 * never sees as props) alive across a filter change — and `page: null` is set
 * explicitly, because carrying page 3 of the old feed into a new one would show
 * an empty result set.
 *
 * A key that is *present* clears its family whatever its value —
 * `clearParamFamily` runs before the null check — so `null`, `[]` and
 * `undefined` are the same thing there, and `taxonomyQuery` returns `null`
 * because that is the honest value for "unset", not because of any difference
 * in the address bar. **Omitting** a key is the only way to leave its family
 * alone, and `apply()` uses that below. See `taxonomyQuery`'s docblock for the
 * measurement.
 *
 * ## `reset: ['pets']` needs `only: ['pets', 'filters']` beside it
 *
 * `pets` ships from `Inertia::scroll()` with `mergeProps: ['pets.data']`, so a
 * visit that re-fetches it **appends**: without the reset, applying a filter
 * from a scrolled feed leaves the old query's cards above the new query's page
 * one. The reset names the prop, `pets` — `reset: ['pets.data']` is the
 * plausible typo and a silent no-op, since the reset matches prop names while
 * `mergeProps` reports dotted paths. Both halves are pinned server-side in
 * `tests/Feature/Http/Controllers/Web/HomeControllerTest.php`, which also
 * records that the visual symptom itself is uncovered here for want of a
 * browser-test package.
 *
 * The reset alone is not safe, though, and that is the half that got missed. In
 * `@inertiajs/core@3.7.0` a non-empty `reset` makes `isPartial()` true, and the
 * request header is `only.concat(reset)` — so `reset: ['pets']` with no `only`
 * sends `X-Inertia-Partial-Data: pets` and the server answers with `pets` and
 * `errors` only. The client merges that over the props it is holding, which
 * means `filters` never comes back: `activeCount` and `hydrate()` both read it,
 * so after any Apply the badge and the sheet's next open described the
 * *previous* query. "Clear all" was the obvious one — the feed cleared, the
 * reopened sheet still showed everything ticked, and Apply re-narrowed it.
 *
 * Measured at the wire on 2026-09-03 against `php artisan serve`, replaying the
 * exact headers the router builds. `X-Inertia-Partial-Data: pets` on
 * `/?category_ids[]=1` answers `["errors","pets"]`; the fixed pair — header
 * `pets,filters,pets`, since `only.concat(reset)` repeats it — answers
 * `["errors","filters","pets"]` with `filters.category_ids == [1]`, and on `/`
 * with every key empty, which is the Clear-all case. `categories` is
 * deliberately absent from `only`: naming a deferred prop resolves it, and the
 * client already holds it. Nothing else can differ — `mergeQuery` preserves
 * `latitude` / `longitude` / `radius`, so `nearby` and `radius` are unchanged,
 * and `listingTypes` / `filterBounds` are static. Same shape as the two sibling
 * feed visits, `pages/Home.vue` and `NearbySearchButton.vue`.
 *
 * ## Categories arrive late
 *
 * `categories` is a deferred prop. The router fetches it off the
 * `deferredProps` announcement, so `<Deferred>` only gates the markup; the
 * sheet issues no reload of its own and needs no `onMounted`.
 *
 * The draft is therefore rebuilt on **either** trigger: the sheet opening, or
 * the category list itself changing identity. The second is what stops a
 * `?category_ids=` in the address bar from being silently dropped when the
 * sheet is opened before the deferred request lands — with no categories to
 * match against, `hydrateSelection` can only return an empty draft, and
 * applying it would clear a filter the visitor never touched. Re-hydrating on
 * arrival cannot lose an edit either: the list only changes on a page visit,
 * which closes the sheet.
 *
 * Apply has the mirror-image hazard, and it is not covered by that watcher: the
 * button lives in `SheetFooter`, outside `<Deferred>`, so it is live while the
 * tree is still drawing skeletons. `taxonomyQuery([], {})` is
 * `{ category_ids: null, breed_ids: null }`, and a *present* key clears its
 * family, so pressing Apply on a slow connection silently dropped a filter the
 * visitor never touched. `taxonomyKeys()` answers `{}` in that state instead,
 * which omits both keys and leaves whatever is in the address bar alone.
 * Disabling Apply would also close it, but it would take the age and
 * adoption-type filters down with it for no reason.
 *
 * Measured 2026-09-03, both halves. Feeding the pre-fix payload through
 * `queryParams({ mergeQuery })` from a start of
 * `?category_ids[]=3&category_ids[]=4&breed_ids[]=9&latitude=30` produced
 * `?latitude=30&age_min=0&age_max=15` — the whole taxonomy gone. Live, in
 * headless Chrome with the deferred `categories` request held open at the
 * network layer (4 skeletons, 0 category rows rendered), opening
 * `/?category_ids[]=1` and pressing Apply now yields
 * `?age_max=15&age_min=0&category_ids[0]=1` and the trigger stays at
 * "Filters (1)".
 *
 * ## The two ends of the age range are always sent
 *
 * Unlike every other key here, `age_min` / `age_max` are never omitted — the
 * slider has no "unset" position, so its resting place is
 * `bounds.default_age_min` … `default_age_max` and that is what a filter change
 * carries. Only "Clear all" removes them.
 *
 * The age state is plain `number` rather than the `InputValue` every text box
 * in this app needs, and that holds only because `AgeRangeSlider` converts by
 * hand. Nothing there uses `v-model`: both inputs are `:value` + `@input`, and
 * the handlers run the DOM value through `Number(...)`. That conversion is
 * **required, not incidental** — `@vue/runtime-dom`'s `vModelText` casts only
 * when the `.number` modifier is present or the input's type is literally
 * `number` (`castToNumber = number || vnode.props.type === "number"`), so a
 * bare `v-model` on a `type="range"` writes the DOM's *string* and this state
 * would quietly become `string | number`. `InputValue` is documented in
 * `resources/js/lib/inputValue.ts` and `resources/js/lib/petForm.ts`, which
 * record what that mistake cost the pet form.
 */
const { filters, bounds, listingTypes, categories } = defineProps<{
    filters: HomeFeedFilters;
    bounds: HomeFeedBounds;
    listingTypes: SelectOption<PetListingType>[];
    /** Deferred — `undefined` until the router's follow-up request lands. */
    categories?: PetCategoryOption[];
}>();

const { isRtl } = useLocale();
const { t } = useTranslations();

const open = ref(false);

const selection = ref<CategorySelection>({});
const listingTypeValues = ref<string[]>([]);
const ageMin = ref(bounds.default_age_min);
const ageMax = ref(bounds.default_age_max);
const vaccinated = ref(false);

function hydrate(): void {
    selection.value = hydrateSelection(categories ?? [], filters);
    listingTypeValues.value = [...filters.listing_types];
    ageMin.value = filters.age_min ?? bounds.default_age_min;
    ageMax.value = filters.age_max ?? bounds.default_age_max;
    vaccinated.value = filters.vaccinated === true;
}

watch([open, () => categories], ([isOpen]) => {
    if (isOpen) {
        hydrate();
    }
});

/**
 * The enum's `label()` is hardcoded English with no `__()` — deliberately, and
 * localising the enums is its own piece of work (.ai/rules/enums.md) — so the
 * label is looked up in the catalogue first and falls back to what the server
 * sent. `t()` returns the key when it misses, which is what that comparison
 * detects.
 *
 * Keyed on `value`, not on the lowercased label the legacy filter used. They
 * are the same three strings today, but `value` is the enum's own backing
 * string and the half that cannot drift; the label is display copy.
 */
function listingTypeLabel(option: SelectOption<PetListingType>): string {
    const key = `listing_types.${option.value}`;
    const translated = t(key);

    return translated === key ? option.label : translated;
}

const listingTypeOptions = computed<FilterOption<string>[]>(() =>
    listingTypes.map((option) => ({
        value: option.value,
        label: listingTypeLabel(option),
    })),
);

/**
 * What the server is filtering on right now, for the trigger's counter.
 *
 * The age range counts as **one** narrowing and only when it is actually
 * narrower than the slider's resting position. Counting `age_min` and `age_max`
 * as two live keys — which they now always are, since the slider always sends
 * both — would put a permanent `(2)` on the trigger after any filter change,
 * including one that selected nothing.
 *
 * This counts the server's `filters`, not the draft, so it is the one place
 * where a **breedless** category named in `category_ids` is visible: the tree
 * structurally cannot represent one (see `CategorySelection`), so it counts
 * here while nothing in the sheet can show or clear it. Unreachable with the
 * seeded taxonomy, where every category has breeds, and a limit of the two-key
 * query rather than of this computed — the alternative, filtering the count
 * against `categories`, would read `0` for every visitor until the deferred
 * prop lands and then jump.
 */
const activeCount = computed(() => {
    const narrowedAge =
        (filters.age_min ?? bounds.default_age_min) !==
            bounds.default_age_min ||
        (filters.age_max ?? bounds.default_age_max) !== bounds.default_age_max;

    return (
        filters.category_ids.length +
        filters.breed_ids.length +
        filters.listing_types.length +
        (narrowedAge ? 1 : 0) +
        (filters.vaccinated === true ? 1 : 0)
    );
});

function visit(query: QueryParams): void {
    router.get(
        home.url({ mergeQuery: { ...query, page: null } }),
        {},
        {
            // `filters` is not optional here. See the docblock: a bare
            // `reset` makes the visit partial, and the server then answers
            // without the prop this component reads back.
            only: ['pets', 'filters'],
            reset: ['pets'],
            // No `preserveState` / `replace`, unlike the two sibling feed
            // visits. Both of those are involuntary — a background geolocation
            // visit and a "clear" affordance — so neither should cost a Back
            // press. Applying a filter is a deliberate act, and Back returning
            // to the previous filter set is the behaviour that act implies.
            // The sheet is remounted by reka on each open, so there is no local
            // state for `preserveState` to protect.
            preserveScroll: false,
            onSuccess: () => (open.value = false),
        },
    );
}

/**
 * The two taxonomy keys, or **no keys at all** while `categories` is in flight.
 *
 * `taxonomyQuery` cannot tell "nothing selected" from "nothing to select from
 * yet", and the two want opposite answers: the first must clear the families,
 * the second must leave them exactly as the address bar has them. `undefined`
 * is the only signal that distinguishes them, and it is a prop, so the check
 * belongs here rather than inside the pure helper.
 */
function taxonomyKeys(): Partial<TaxonomyQuery> {
    return categories === undefined
        ? {}
        : taxonomyQuery(categories, selection.value);
}

function apply(): void {
    visit({
        ...taxonomyKeys(),
        listing_types: listingTypeValues.value.length
            ? listingTypeValues.value
            : null,
        age_min: ageMin.value,
        age_max: ageMax.value,
        vaccinated: vaccinated.value ? 1 : null,
    });
}

function clear(): void {
    visit({
        category_ids: null,
        breed_ids: null,
        listing_types: null,
        age_min: null,
        age_max: null,
        vaccinated: null,
    });
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetTrigger as-child>
            <Button variant="outline">
                <SlidersHorizontal class="size-4" />
                {{ t('home.filters') }}
                <span v-if="activeCount > 0" class="text-muted-foreground">
                    ({{ activeCount }})
                </span>
            </Button>
        </SheetTrigger>

        <!--
            The dialog has a title and deliberately no description: the legacy
            filter had no descriptive line, there is no catalogue key for one,
            and inventing English copy for a screen that is otherwise fully
            translated is worse than letting the title carry it.

            `:aria-describedby="undefined"` is the opt-out, and it has to be the
            JS value, not the string `"undefined"` the warning's own text spells
            out. reka checks `getAttribute('aria-describedby')` and warns when it
            finds a value naming no element — the literal string is such a value,
            so writing it *keeps* the warning while looking like the fix.
            Measured both ways in a development build.
        -->
        <SheetContent
            :side="isRtl ? 'right' : 'left'"
            class="w-[350px] gap-0 p-0 sm:w-[400px] sm:max-w-[400px]"
            :aria-describedby="undefined"
        >
            <!--
                `pr-12` is the one physical-direction utility in a file that is
                otherwise entirely logical, and it is deliberate rather than an
                oversight: it reserves room for `ui/sheet`'s close button, which
                `SheetContent` pins at physical `right-4` in **both**
                directions. If that button is ever moved to a logical `end-4`,
                this has to become `pe-12` in the same change or one direction
                silently loses the gap.
            -->
            <SheetHeader
                class="border-border flex-row items-center justify-between gap-2 border-b p-4 pr-12"
            >
                <SheetTitle class="flex items-center gap-2 text-lg font-bold">
                    <SlidersHorizontal class="text-primary size-5" />
                    {{ t('home.filters') }}
                </SheetTitle>

                <Button
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground shrink-0 text-xs"
                    @click="clear"
                >
                    {{ t('home.clear_all') }}
                </Button>
            </SheetHeader>

            <div class="flex-1 space-y-6 overflow-y-auto p-4">
                <FilterSection :title="t('home.animal_type')">
                    <Deferred data="categories">
                        <template #fallback>
                            <div class="space-y-2" aria-hidden="true">
                                <Skeleton
                                    v-for="row in 4"
                                    :key="row"
                                    class="h-11 w-full rounded-xl"
                                />
                            </div>
                        </template>

                        <AnimalTypeFilter
                            v-model="selection"
                            :categories="categories ?? []"
                        />
                    </Deferred>
                </FilterSection>

                <Separator />

                <FilterSection :title="t('home.age_range')">
                    <AgeRangeSlider
                        v-model:min="ageMin"
                        v-model:max="ageMax"
                        :ceiling="bounds.max_age_years"
                        id-prefix="filter-age"
                    />
                </FilterSection>

                <Separator />

                <FilterSection :title="t('home.adoption_type')">
                    <FilterCheckboxList
                        v-model="listingTypeValues"
                        :options="listingTypeOptions"
                        id-prefix="filter-listing-type"
                    />
                </FilterSection>

                <Separator />

                <FilterSection :title="t('home.vaccination_status')">
                    <FilterCheckboxRow
                        id="filter-vaccinated"
                        :label="t('home.vaccinated_only')"
                        :checked="vaccinated"
                        @update:checked="vaccinated = $event"
                    />
                </FilterSection>
            </div>

            <SheetFooter class="border-border border-t p-4">
                <Button class="w-full" @click="apply">
                    {{ t('home.apply_filters') }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
