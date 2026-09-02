<script setup lang="ts">
import { Deferred, router } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FilterCheckboxList from '@/components/pets/FilterCheckboxList.vue';
import type { FilterOption } from '@/components/pets/FilterCheckboxList.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/composables/useLocale';
import { type InputValue, trimmedInput } from '@/lib/inputValue';
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
 * The feed's filter sheet.
 *
 * Filters live in the query string, not in component state: `home` echoes the
 * normalised bag back as `filters`, so the sheet is seeded from the server's
 * answer and a shared URL reproduces the same feed. Applying is an ordinary GET
 * visit.
 *
 * Every key is written through `mergeQuery`, which starts from the current
 * query string and clears only the families being set. That is what keeps a
 * nearby search (`latitude` / `longitude` / `radius`, which this component
 * never sees as props) alive across a filter change — and `page: null` is set
 * explicitly, because carrying page 3 of the old feed into a new one would show
 * an empty result set.
 *
 * `categories` is a deferred prop. The router fetches it by itself off the
 * `deferredProps` announcement, so `<Deferred>` only gates the markup; the
 * sheet issues no reload of its own and needs no `onMounted`.
 *
 * The two age boxes are `inputmode="decimal"` text, not `type="number"`, and
 * their refs are `InputValue`. A numeric input's `v-model` casts the DOM value
 * unconditionally, so these refs held a `number` the moment anyone typed a
 * bound and `apply()` threw on `.trim()` before the visit was ever made — the
 * same defect that made the pet form unpublishable. See `lib/inputValue`.
 */
const { filters, bounds, listingTypes, categories } = defineProps<{
    filters: HomeFeedFilters;
    bounds: HomeFeedBounds;
    listingTypes: SelectOption<PetListingType>[];
    /** Deferred — `undefined` until the router's follow-up request lands. */
    categories?: PetCategoryOption[];
}>();

const { isRtl } = useLocale();

const open = ref(false);

const categoryIds = ref<(string | number)[]>([]);
const breedIds = ref<(string | number)[]>([]);
const listingTypeValues = ref<(string | number)[]>([]);
const ageMin = ref<InputValue>('');
const ageMax = ref<InputValue>('');
const vaccinated = ref(false);

/** Reseed from the server's normalised bag every time the sheet is opened. */
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    categoryIds.value = [...filters.category_ids];
    breedIds.value = [...filters.breed_ids];
    listingTypeValues.value = [...filters.listing_types];
    ageMin.value = filters.age_min === null ? '' : String(filters.age_min);
    ageMax.value = filters.age_max === null ? '' : String(filters.age_max);
    vaccinated.value = filters.vaccinated === true;
});

const categoryOptions = computed<FilterOption[]>(() =>
    (categories ?? []).map((category) => ({
        value: category.id,
        label: category.name,
    })),
);

/**
 * Breeds are offered only for the categories that are actually selected —
 * every breed of every category at once is a list nobody reads.
 */
const breedOptions = computed<FilterOption[]>(() =>
    (categories ?? [])
        .filter((category) => categoryIds.value.includes(category.id))
        .flatMap((category) => category.breeds ?? [])
        .map((breed) => ({ value: breed.id, label: breed.name })),
);

/** A breed whose category was just deselected must not stay in the bag. */
watch(breedOptions, (options) => {
    const available = new Set(options.map((option) => option.value));

    breedIds.value = breedIds.value.filter((id) => available.has(id));
});

const listingTypeOptions = computed<FilterOption[]>(() =>
    listingTypes.map((option) => ({
        value: option.value,
        label: option.label,
    })),
);

const activeCount = computed(
    () =>
        filters.category_ids.length +
        filters.breed_ids.length +
        filters.listing_types.length +
        (filters.age_min === null ? 0 : 1) +
        (filters.age_max === null ? 0 : 1) +
        (filters.vaccinated === true ? 1 : 0),
);

function visit(query: QueryParams): void {
    router.get(
        home.url({ mergeQuery: { ...query, page: null } }),
        {},
        { preserveScroll: false, onSuccess: () => (open.value = false) },
    );
}

function apply(): void {
    visit({
        category_ids: categoryIds.value.length ? categoryIds.value : null,
        breed_ids: breedIds.value.length ? breedIds.value : null,
        listing_types: listingTypeValues.value.length
            ? listingTypeValues.value
            : null,
        age_min: trimmedInput(ageMin.value) || null,
        age_max: trimmedInput(ageMax.value) || null,
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
                Filters
                <span v-if="activeCount > 0" class="text-muted-foreground">
                    ({{ activeCount }})
                </span>
            </Button>
        </SheetTrigger>

        <SheetContent
            :side="isRtl ? 'right' : 'left'"
            class="overflow-y-auto p-6"
        >
            <SheetHeader class="p-0">
                <SheetTitle>Filter listings</SheetTitle>
                <SheetDescription>
                    Narrow the feed down. Filters live in the address bar, so
                    the result is shareable.
                </SheetDescription>
            </SheetHeader>

            <div class="space-y-6 py-4">
                <Deferred data="categories">
                    <template #fallback>
                        <div class="space-y-3">
                            <Skeleton class="h-4 w-24" />
                            <Skeleton class="h-4 w-40" />
                            <Skeleton class="h-4 w-32" />
                            <Skeleton class="h-4 w-36" />
                        </div>
                    </template>

                    <div class="space-y-6">
                        <section class="space-y-3">
                            <h3 class="text-sm font-medium">Category</h3>
                            <FilterCheckboxList
                                v-model="categoryIds"
                                :options="categoryOptions"
                                id-prefix="filter-category"
                            />
                        </section>

                        <section
                            v-if="breedOptions.length > 0"
                            class="space-y-3"
                        >
                            <h3 class="text-sm font-medium">Breed</h3>
                            <FilterCheckboxList
                                v-model="breedIds"
                                :options="breedOptions"
                                id-prefix="filter-breed"
                            />
                        </section>
                    </div>
                </Deferred>

                <Separator />

                <section class="space-y-3">
                    <h3 class="text-sm font-medium">Listing type</h3>
                    <FilterCheckboxList
                        v-model="listingTypeValues"
                        :options="listingTypeOptions"
                        id-prefix="filter-listing-type"
                    />
                </section>

                <Separator />

                <section class="space-y-3">
                    <h3 class="text-sm font-medium">
                        Age (up to {{ bounds.max_age_years }} years)
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="filter-age-min">From</Label>
                            <Input
                                id="filter-age-min"
                                v-model="ageMin"
                                inputmode="decimal"
                                :placeholder="String(bounds.default_age_min)"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="filter-age-max">To</Label>
                            <Input
                                id="filter-age-max"
                                v-model="ageMax"
                                inputmode="decimal"
                                :placeholder="String(bounds.default_age_max)"
                            />
                        </div>
                    </div>
                </section>

                <Separator />

                <div class="flex items-center gap-2">
                    <Checkbox
                        id="filter-vaccinated"
                        :model-value="vaccinated"
                        @update:model-value="
                            (checked) => (vaccinated = checked === true)
                        "
                    />
                    <Label
                        for="filter-vaccinated"
                        class="cursor-pointer font-normal"
                    >
                        Vaccinated only
                    </Label>
                </div>
            </div>

            <SheetFooter class="flex-row gap-2 p-0">
                <Button variant="outline" class="flex-1" @click="clear">
                    Clear
                </Button>
                <Button class="flex-1" @click="apply">Apply</Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
