<script setup lang="ts">
import PetCard from '@/components/web/PetCard.vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { Plus, Filter as FilterIcon } from 'lucide-vue-next';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import Filter from '@/components/web/Filter.vue';
import type {
    FilterCategory,
    FilterDefaults,
    HomeFilters,
    ListingTypeOption,
} from '@/components/web/Filter.vue';
import { route } from 'ziggy-js';
import { useInertiaInfiniteScroll } from '@/composables/useInertiaInfiniteScroll';
import InfiniteScroll from '@/components/web/InfiniteScroll.vue';
import { useAuthUser } from '@/composables/useAuthUser';
import { useTranslations } from '@/composables/useTranslations';
import { useGeolocation } from '@/composables/useGeolocation';
import { computed, onMounted, onUnmounted, ref, toRef } from 'vue';

const LOCATION_VISIT_KEY = 'petconnect:home-location-visit';

const user = useAuthUser();
const { t, dir } = useTranslations();
const { requestLocation } = useGeolocation();

type CategoryProp =
    | FilterCategory[]
    | { data: FilterCategory[] }
    | null
    | undefined;

const props = defineProps<{
    pets: any;
    reportReasons: Array<{ value: string; label: string }>;
    nearby?: boolean;
    radius?: number | null;
    defaultRadius: number;
    maxRadius: number;
    categories?: CategoryProp;
    listingTypes?: ListingTypeOption[];
    filters?: HomeFilters;
    filterDefaults?: FilterDefaults;
}>();

const filterSheetOpen = ref(false);

const filterSheetSide = computed(() =>
    dir.value === 'rtl' ? 'right' : 'left',
);

const heading = computed(() =>
    props.nearby ? t('home.nearby_pets') : t('home.discover_pets'),
);

const filterCategories = computed<FilterCategory[]>(() => {
    const raw = props.categories;

    if (!raw) {
        return [];
    }

    if (Array.isArray(raw)) {
        return raw;
    }

    return raw.data ?? [];
});

const activeFilters = computed<HomeFilters>(
    () =>
        props.filters ?? {
            category_ids: [],
            breed_ids: [],
            age_min: null,
            age_max: null,
            listing_types: [],
            vaccinated: null,
        },
);

const defaults = computed<FilterDefaults>(
    () =>
        props.filterDefaults ?? {
            age_min: 0,
            age_max: 15,
            max_age: 15,
        },
);

const locationQuery = computed(() => {
    if (!props.nearby) {
        return {};
    }

    const query: Record<string, number> = {};

    const params = new URLSearchParams(window.location.search);
    const latitude = params.get('latitude');
    const longitude = params.get('longitude');

    if (latitude) {
        query.latitude = Number(latitude);
    }

    if (longitude) {
        query.longitude = Number(longitude);
    }

    if (props.radius != null) {
        query.radius = props.radius;
    }

    return query;
});

const filterQuery = computed(() => {
    const filters = activeFilters.value;
    const query: Record<string, unknown> = {};

    if (filters.category_ids.length > 0) {
        query.category_ids = filters.category_ids;
    }

    if (filters.breed_ids.length > 0) {
        query.breed_ids = filters.breed_ids;
    }

    if (filters.age_min !== null) {
        query.age_min = filters.age_min;
    }

    if (filters.age_max !== null) {
        query.age_max = filters.age_max;
    }

    if (filters.listing_types.length > 0) {
        query.listing_types = filters.listing_types;
    }

    if (filters.vaccinated === true) {
        query.vaccinated = 1;
    }

    return query;
});

const {
    items: allPets,
    nextUrl,
    isLoading,
    loadMore,
} = useInertiaInfiniteScroll<any>(toRef(props, 'pets'), 'pets');

const isResolvingLocation = ref(false);
let cancelled = false;

onMounted(async () => {
    if (props.nearby) {
        return;
    }

    if (sessionStorage.getItem(LOCATION_VISIT_KEY)) {
        return;
    }

    // Claim the visit slot immediately so remounts from filter applies
    // cannot start a second geolocation/router cycle.
    sessionStorage.setItem(LOCATION_VISIT_KEY, '1');
    isResolvingLocation.value = true;

    try {
        const coords = await requestLocation();

        if (cancelled || !coords) {
            return;
        }

        router.get(
            route('home'),
            {
                ...filterQuery.value,
                latitude: coords.latitude,
                longitude: coords.longitude,
                radius: props.defaultRadius,
            },
            {
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
            },
        );
    } finally {
        if (!cancelled) {
            isResolvingLocation.value = false;
        }
    }
});

onUnmounted(() => {
    cancelled = true;
});
</script>

<template>
    <MainLayout>
        <div class="mx-auto w-full max-w-7xl px-6 py-8">
            <div class="mb-6">
                <Sheet v-model:open="filterSheetOpen">
                    <SheetTrigger as-child>
                        <Button variant="outline" class="gap-2">
                            <FilterIcon class="h-4 w-4" />
                            <span>{{ t('home.filters') }}</span>
                        </Button>
                    </SheetTrigger>
                    <SheetContent
                        :side="filterSheetSide"
                        class="w-[350px] p-0 sm:w-[400px]"
                    >
                        <div class="h-full overflow-y-auto">
                            <Filter
                                :categories="filterCategories"
                                :listing-types="listingTypes ?? []"
                                :filters="activeFilters"
                                :filter-defaults="defaults"
                                :location-query="locationQuery"
                                @applied="filterSheetOpen = false"
                            />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
            <div class="w-full">
                <div
                    class="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
                >
                    <h2
                        class="text-2xl font-bold text-primary dark:text-primary-400"
                    >
                        {{ heading }}
                    </h2>
                    <Link
                        v-if="user?.email_verified_at"
                        :href="route('pets.create')"
                        :class="
                            cn(
                                buttonVariants(),
                                'cursor-pointer gap-2 bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:from-violet-600 hover:to-fuchsia-600',
                            )
                        "
                    >
                        <Plus class="h-5 w-5" />
                        {{ t('home.create_post') }}
                    </Link>
                </div>
                <section
                    class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <template v-if="allPets.length > 0">
                        <PetCard
                            v-for="pet in allPets"
                            :key="pet.id"
                            :pet="pet"
                            :report-reasons="reportReasons"
                        />
                    </template>
                    <div
                        v-else
                        class="col-span-full py-12 text-center text-gray-500"
                    >
                        <p>
                            {{
                                nearby
                                    ? t('home.no_nearby_pets')
                                    : t('home.no_pets_found')
                            }}
                        </p>
                    </div>
                </section>

                <InfiniteScroll
                    v-if="allPets.length > 0"
                    :has-more="!!nextUrl"
                    :is-loading="isLoading || isResolvingLocation"
                    @load-more="loadMore"
                />
            </div>
        </div>
    </MainLayout>
</template>

<style scoped></style>
