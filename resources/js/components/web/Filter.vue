<template>
    <aside
        class="scrollbar-thin scrollbar-track-transparent scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-700 hover:scrollbar-thumb-gray-400 dark:hover:scrollbar-thumb-gray-600 scrollbar-thumb-rounded-full sticky flex h-[calc(100vh)] flex-col overflow-y-auto overflow-x-hidden border-e border-gray-200 bg-white py-4 ps-6 transition-colors duration-200 dark:border-gray-700 dark:bg-gray-900"
        style="scrollbar-gutter: stable"
    >
        <div class="mt-8 flex-1 space-y-8 pb-24">
            <div>
                <div class="mb-6 flex items-center justify-between pe-6">
                    <h2 class="flex items-center gap-2 text-lg font-bold">
                        <FilterIcon class="h-5 w-5 text-primary" />
                        {{ t('home.filters') }}
                    </h2>
                    <button
                        type="button"
                        @click="clearAllFilters"
                        class="rounded-md px-3 py-1 text-xs text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                    >
                        {{ t('home.clear_all') }}
                    </button>
                </div>
            </div>

            <div class="space-y-6 pe-6">
                <!-- Animal Type → Breeds (collapsible tree) -->
                <div class="mt-6">
                    <h3
                        class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                    >
                        {{ t('home.animal_type') }}
                    </h3>
                    <div class="space-y-2">
                        <Collapsible
                            v-for="category in categories"
                            :key="category.id"
                            v-model:open="expandedCategories[category.id]"
                            class="rounded-xl"
                        >
                            <div
                                class="group flex items-center gap-2 rounded-xl p-2 transition-colors hover:bg-gray-100 dark:hover:bg-gray-800"
                            >
                                <CollapsibleTrigger as-child>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground flex h-8 w-8 shrink-0 items-center justify-center rounded-md"
                                        :aria-label="
                                            expandedCategories[category.id]
                                                ? t('home.collapse_breeds')
                                                : t('home.expand_breeds')
                                        "
                                    >
                                        <ChevronRight
                                            class="h-4 w-4 transition-transform duration-200"
                                            :class="{
                                                'rotate-90':
                                                    expandedCategories[
                                                        category.id
                                                    ],
                                            }"
                                        />
                                    </button>
                                </CollapsibleTrigger>

                                <label
                                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-3 py-1 pe-1"
                                >
                                    <input
                                        type="checkbox"
                                        class="checkbox-custom"
                                        :checked="isAllBreedsSelected(category)"
                                        :indeterminate.prop="
                                            isSomeBreedsSelected(category)
                                        "
                                        @change="toggleCategory(category)"
                                    />
                                    <component
                                        :is="categoryIcon(category.slug)"
                                        class="h-4 w-4 shrink-0"
                                    />
                                    <span
                                        class="truncate text-sm font-medium text-gray-800 dark:text-gray-200"
                                    >
                                        {{ category.name }}
                                    </span>
                                    <span
                                        v-if="selectedBreedCount(category) > 0"
                                        class="ms-auto rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                                    >
                                        {{ selectedBreedCount(category)
                                        }}{{
                                            category.breeds.length
                                                ? `/${category.breeds.length}`
                                                : ''
                                        }}
                                    </span>
                                </label>
                            </div>

                            <CollapsibleContent
                                class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 overflow-hidden"
                            >
                                <div
                                    v-if="category.breeds.length > 0"
                                    class="border-border ms-10 space-y-1 border-s py-1 ps-3"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2 px-2 py-1"
                                    >
                                        <button
                                            type="button"
                                            class="text-xs font-medium text-primary transition-colors hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline dark:disabled:text-gray-500"
                                            :disabled="
                                                selectedBreedCount(category) ===
                                                0
                                            "
                                            @click="
                                                clearCategoryBreedSelection(
                                                    category.id,
                                                )
                                            "
                                        >
                                            {{ t('home.unselect_all') }}
                                        </button>
                                    </div>
                                    <label
                                        v-for="breed in category.breeds"
                                        :key="breed.id"
                                        class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-lg p-2 transition-colors"
                                    >
                                        <input
                                            type="checkbox"
                                            class="checkbox-custom"
                                            :checked="
                                                isBreedSelected(
                                                    category.id,
                                                    breed.id,
                                                )
                                            "
                                            @change="
                                                toggleBreed(category, breed.id)
                                            "
                                        />
                                        <span class="text-sm">{{
                                            breed.name
                                        }}</span>
                                    </label>
                                </div>
                                <p
                                    v-else
                                    class="text-muted-foreground ms-10 py-2 ps-3 text-xs"
                                >
                                    {{ t('home.no_breeds') }}
                                </p>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                </div>

                <div class="border-border border-t"></div>

                <!-- Age Range -->
                <div>
                    <h3
                        class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                    >
                        {{ t('home.age_range') }}
                    </h3>
                    <div class="px-2">
                        <div class="relative flex h-8 w-full items-center">
                            <div class="relative">
                                <div class="track"></div>
                                <div
                                    class="range"
                                    :style="{
                                        left: `${(ageRange[0] / maxAge) * 100}%`,
                                        right: `${100 - (ageRange[1] / maxAge) * 100}%`,
                                    }"
                                ></div>
                            </div>

                            <input
                                type="range"
                                v-model.number="ageRange[0]"
                                :min="0"
                                :max="maxAge"
                                step="1"
                                class="range-input min-value"
                                @input="adjustMinRange"
                            />

                            <input
                                type="range"
                                v-model.number="ageRange[1]"
                                :min="0"
                                :max="maxAge"
                                step="1"
                                class="range-input max-value"
                                @input="adjustMaxRange"
                            />
                        </div>
                        <div
                            class="text-muted-foreground mt-3 flex justify-between text-xs"
                        >
                            <span
                                >{{ ageRange[0] }}
                                {{
                                    ageRange[0] === 1
                                        ? t('home.year')
                                        : t('home.years')
                                }}</span
                            >
                            <span
                                >{{ ageRange[1] }}
                                {{
                                    ageRange[1] === 1
                                        ? t('home.year')
                                        : t('home.years')
                                }}</span
                            >
                        </div>
                    </div>
                </div>

                <div class="border-border border-t"></div>

                <!-- Adoption / Listing Type -->
                <div>
                    <h3
                        class="text-muted-foreground mb-4 text-sm font-semibold uppercase tracking-wide"
                    >
                        {{ t('home.adoption_type') }}
                    </h3>
                    <div class="space-y-3">
                        <label
                            v-for="type in listingTypes"
                            :key="type.value"
                            class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-xl p-3 transition-colors"
                        >
                            <input
                                type="checkbox"
                                :value="type.value"
                                v-model="selectedListingTypes"
                                class="checkbox-custom"
                            />
                            <span class="text-sm font-medium">{{
                                listingTypeLabel(type)
                            }}</span>
                        </label>
                    </div>
                </div>

                <div class="border-border border-t"></div>

                <!-- Vaccination -->
                <div>
                    <h3
                        class="text-muted-foreground mb-4 text-sm font-semibold uppercase tracking-wide"
                    >
                        {{ t('home.vaccination_status') }}
                    </h3>
                    <label
                        class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-xl p-3 transition-colors"
                    >
                        <input
                            type="checkbox"
                            v-model="vaccinatedOnly"
                            class="checkbox-custom"
                        />
                        <span class="text-sm font-medium">{{
                            t('home.vaccinated_only')
                        }}</span>
                    </label>
                </div>
            </div>
        </div>

        <div
            class="border-border sticky bottom-0 border-t bg-white px-0 pe-6 pt-4 dark:bg-gray-900"
        >
            <Button class="w-full" type="button" @click="applyFilters">
                {{ t('home.apply_filters') }}
            </Button>
        </div>
    </aside>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import {
    Bird,
    Cat,
    ChevronRight,
    Dog,
    Filter as FilterIcon,
    Trophy,
} from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useTranslations } from '@/composables/useTranslations';

export type FilterBreed = {
    id: number;
    name: string;
};

export type FilterCategory = {
    id: number;
    name: string;
    slug?: string | null;
    breeds: FilterBreed[];
};

export type ListingTypeOption = {
    value: number;
    label: string;
};

export type HomeFilters = {
    category_ids: number[];
    breed_ids: number[];
    age_min: number | null;
    age_max: number | null;
    listing_types: number[];
    vaccinated: boolean | null;
};

export type FilterDefaults = {
    age_min: number;
    age_max: number;
    max_age: number;
};

const props = withDefaults(
    defineProps<{
        categories?: FilterCategory[];
        listingTypes?: ListingTypeOption[];
        filters?: HomeFilters;
        filterDefaults?: FilterDefaults;
        locationQuery?: Record<string, number | string | undefined | null>;
    }>(),
    {
        categories: () => [],
        listingTypes: () => [],
        filters: () => ({
            category_ids: [],
            breed_ids: [],
            age_min: null,
            age_max: null,
            listing_types: [],
            vaccinated: null,
        }),
        filterDefaults: () => ({
            age_min: 0,
            age_max: 15,
            max_age: 15,
        }),
        locationQuery: () => ({}),
    },
);

const emit = defineEmits<{
    applied: [];
}>();

const { t } = useTranslations();

const categories = computed(() => props.categories ?? []);
const listingTypes = computed(() => props.listingTypes ?? []);
const maxAge = computed(() => props.filterDefaults.max_age || 15);

/** Selected breed ids keyed by category id. Category checkbox mirrors this set. */
const selectedBreedsByCategory = reactive<Record<number, number[]>>({});
/** Independent expand/collapse state for each category panel. */
const expandedCategories = reactive<Record<number, boolean>>({});

const ageRange = ref<[number, number]>([
    props.filterDefaults.age_min,
    props.filterDefaults.age_max,
]);
const selectedListingTypes = ref<number[]>([]);
const vaccinatedOnly = ref(false);

function categoryIcon(slug?: string | null) {
    switch (slug) {
        case 'dogs':
            return Dog;
        case 'cats':
            return Cat;
        case 'birds':
            return Bird;
        case 'horses':
            return Trophy;
        default:
            return Dog;
    }
}

function listingTypeLabel(type: ListingTypeOption): string {
    const key = `listing_types.${type.label.toLowerCase()}`;
    const translated = t(key);

    return translated === key ? type.label : translated;
}

function categoryBreedIds(category: FilterCategory): number[] {
    return category.breeds.map((breed) => breed.id);
}

function selectedBreedIdsFor(categoryId: number): number[] {
    return selectedBreedsByCategory[categoryId] ?? [];
}

function selectedBreedCount(category: FilterCategory): number {
    return selectedBreedIdsFor(category.id).length;
}

function isBreedSelected(categoryId: number, breedId: number): boolean {
    return selectedBreedIdsFor(categoryId).includes(breedId);
}

function isAllBreedsSelected(category: FilterCategory): boolean {
    const breedIds = categoryBreedIds(category);

    if (breedIds.length === 0) {
        return false;
    }

    const selected = selectedBreedIdsFor(category.id);

    return (
        selected.length === breedIds.length &&
        breedIds.every((id) => selected.includes(id))
    );
}

function isSomeBreedsSelected(category: FilterCategory): boolean {
    const selected = selectedBreedIdsFor(category.id);

    return selected.length > 0 && !isAllBreedsSelected(category);
}

function expandCategory(categoryId: number): void {
    expandedCategories[categoryId] = true;
}

function clearCategoryBreedSelection(categoryId: number): void {
    delete selectedBreedsByCategory[categoryId];
}

function selectAllBreeds(category: FilterCategory): void {
    selectedBreedsByCategory[category.id] = categoryBreedIds(category);
}

/**
 * Parent checkbox behavior (standard nested tree):
 * - unchecked → select all breeds and expand
 * - checked / indeterminate → clear all breeds
 */
function toggleCategory(category: FilterCategory): void {
    if (isAllBreedsSelected(category) || isSomeBreedsSelected(category)) {
        clearCategoryBreedSelection(category.id);

        return;
    }

    if (category.breeds.length === 0) {
        return;
    }

    selectAllBreeds(category);
    expandCategory(category.id);
}

function toggleBreed(category: FilterCategory, breedId: number): void {
    const current = [...selectedBreedIdsFor(category.id)];
    const index = current.indexOf(breedId);

    if (index >= 0) {
        current.splice(index, 1);
    } else {
        current.push(breedId);
    }

    if (current.length === 0) {
        clearCategoryBreedSelection(category.id);

        return;
    }

    selectedBreedsByCategory[category.id] = current;
}

function hydrateFromFilters(filters: HomeFilters): void {
    const breedIds = new Set(filters.breed_ids ?? []);
    const selectedCategories = new Set(filters.category_ids ?? []);
    const nextBreedsByCategory: Record<number, number[]> = {};
    const nextExpanded: Record<number, boolean> = {
        ...expandedCategories,
    };

    for (const category of categories.value) {
        const matchedBreeds = category.breeds
            .filter((breed) => breedIds.has(breed.id))
            .map((breed) => breed.id);

        if (matchedBreeds.length > 0) {
            nextBreedsByCategory[category.id] = matchedBreeds;
            nextExpanded[category.id] = true;
        } else if (selectedCategories.has(category.id)) {
            nextBreedsByCategory[category.id] = categoryBreedIds(category);
            nextExpanded[category.id] = true;
        }
    }

    Object.keys(selectedBreedsByCategory).forEach((key) => {
        delete selectedBreedsByCategory[Number(key)];
    });
    Object.assign(selectedBreedsByCategory, nextBreedsByCategory);

    for (const category of categories.value) {
        expandedCategories[category.id] = Boolean(nextExpanded[category.id]);
    }

    ageRange.value = [
        filters.age_min ?? props.filterDefaults.age_min,
        filters.age_max ?? props.filterDefaults.age_max,
    ];
    selectedListingTypes.value = [...(filters.listing_types ?? [])];
    vaccinatedOnly.value = filters.vaccinated === true;
}

watch(
    categories,
    (cats) => {
        for (const category of cats) {
            if (!(category.id in expandedCategories)) {
                expandedCategories[category.id] = false;
            }
        }
    },
    { immediate: true },
);

watch(
    () => props.filters,
    (filters) => {
        if (filters) {
            hydrateFromFilters(filters);
        }
    },
    { immediate: true, deep: true },
);

function adjustMinRange(): void {
    if (ageRange.value[0] > ageRange.value[1]) {
        ageRange.value[0] = ageRange.value[1];
    }
}

function adjustMaxRange(): void {
    if (ageRange.value[1] < ageRange.value[0]) {
        ageRange.value[1] = ageRange.value[0];
    }
}

function buildFilterQuery(): Record<string, unknown> {
    const categoryIds: number[] = [];
    const breedIds: number[] = [];

    for (const category of categories.value) {
        const selected = selectedBreedIdsFor(category.id);

        if (selected.length === 0) {
            continue;
        }

        if (isAllBreedsSelected(category)) {
            categoryIds.push(category.id);
        } else {
            breedIds.push(...selected);
        }
    }

    const query: Record<string, unknown> = {
        ...props.locationQuery,
    };

    if (categoryIds.length > 0) {
        query.category_ids = categoryIds;
    }

    if (breedIds.length > 0) {
        query.breed_ids = breedIds;
    }

    query.age_min = ageRange.value[0];
    query.age_max = ageRange.value[1];

    if (selectedListingTypes.value.length > 0) {
        query.listing_types = selectedListingTypes.value;
    }

    if (vaccinatedOnly.value) {
        query.vaccinated = 1;
    }

    return query;
}

function visitHome(query: Record<string, unknown>): void {
    router.get(route('home'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: [
            'pets',
            'nearby',
            'radius',
            'filters',
            'categories',
            'listingTypes',
            'filterDefaults',
        ],
    });

    emit('applied');
}

function applyFilters(): void {
    visitHome(buildFilterQuery());
}

function clearAllFilters(): void {
    Object.keys(selectedBreedsByCategory).forEach((key) => {
        delete selectedBreedsByCategory[Number(key)];
    });
    ageRange.value = [
        props.filterDefaults.age_min,
        props.filterDefaults.age_max,
    ];
    selectedListingTypes.value = [];
    vaccinatedOnly.value = false;

    visitHome({ ...props.locationQuery });
}
</script>

<style scoped>
.checkbox-custom {
    appearance: none;
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(42, 57, 81, 0.5);
    border-radius: 0.5rem;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
    flex-shrink: 0;
    background-color: white;
}

.checkbox-custom:hover {
    border-color: #8a2ce2ff;
}

.checkbox-custom:checked {
    background-color: #8a2ce2ff;
    border-color: #8a2ce2ff;
}

.checkbox-custom:checked::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(45deg);
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
}

.checkbox-custom:focus {
    outline: none;
    box-shadow: 0 0 0 2px hsl(var(--primary) / 0.2);
}

.checkbox-custom:indeterminate {
    background-color: #8a2ce2ff;
    border-color: #8a2ce2ff;
}

.checkbox-custom:indeterminate::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 2px;
    background-color: white;
    border: 0;
}

.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: hsl(var(--muted-foreground) / 0.2);
    border-radius: 3px;
    transition: background-color 0.2s ease;
}

.scrollbar-thin:hover::-webkit-scrollbar-thumb {
    background-color: hsl(var(--muted-foreground) / 0.3);
}

.scrollbar-thin {
    scrollbar-width: thin;
    scrollbar-color: hsl(var(--muted-foreground) / 0.2) transparent;
}

.scrollbar-thin:hover {
    scrollbar-color: hsl(var(--muted-foreground) / 0.3) transparent;
}

.range-input {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 24px;
    position: absolute;
    top: 0;
    left: 0;
    margin: 0;
    background: transparent;
    pointer-events: none;
}

.range-input::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    top: -7px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    cursor: pointer;
    pointer-events: auto;
    position: relative;
    z-index: 10;
    border: 3px solid #8a2ce2;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.range-input::-webkit-slider-thumb:hover {
    transform: scale(1.1);
    box-shadow: 0 0 0 4px rgba(138, 44, 226, 0.2);
}

.relative {
    position: relative;
    width: 100%;
    height: 8px;
}

.track {
    position: absolute;
    height: 100%;
    width: 100%;
    background-color: #e5e7eb;
    border-radius: 4px;
}

.range {
    position: absolute;
    height: 100%;
    background-color: #8a2ce2;
    border-radius: 4px;
    z-index: 2;
}

.range-input::-webkit-slider-runnable-track {
    -webkit-appearance: none;
    background: transparent;
}

.range-input::-moz-range-track {
    background: transparent;
}

.range-input:nth-of-type(2)::-webkit-slider-thumb {
    z-index: 20;
}

.range-input:nth-of-type(2)::-moz-range-thumb {
    z-index: 20;
}
</style>
