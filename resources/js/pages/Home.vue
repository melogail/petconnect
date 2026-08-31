<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import NearbySearchButton from '@/components/pets/NearbySearchButton.vue';
import PetFeed from '@/components/pets/PetFeed.vue';
import PetFilterSheet from '@/components/pets/PetFilterSheet.vue';
import type {
    HomeFeedBounds,
    HomeFeedFilters,
    Paginated,
    PetCard,
    PetCategoryOption,
    PetListingType,
    SelectOption,
} from '@/types';

/**
 * The public discovery feed.
 *
 * Two of the props are not plain props and the page is built around that:
 *
 * - `pets` comes from `Inertia::scroll()`. The payload carries
 *   `mergeProps: ["pets.data"]` and the scroll cursor, so `<InfiniteScroll>`
 *   inside `PetFeed` appends page 2 into the list already on screen. Nothing
 *   here reloads `pets` by hand.
 * - `categories` comes from `Inertia::defer()`. It is announced in
 *   `deferredProps` and fetched by the router in one follow-up request, so the
 *   filter sheet gates on `<Deferred>` and the page issues no `onMounted`
 *   fetch. It stays `undefined` until that request lands.
 */
const { filters, nearby } = defineProps<{
    pets: Paginated<PetCard>;
    filters: HomeFeedFilters;
    nearby: boolean;
    radius: number | null;
    listingTypes: SelectOption<PetListingType>[];
    filterBounds: HomeFeedBounds;
    categories?: PetCategoryOption[];
}>();

const heading = computed(() => (nearby ? 'Pets near you' : 'Discover pets'));

const description = computed(() => {
    if (nearby) {
        return 'Sorted by how close each listing is to you.';
    }

    return filters.vaccinated === true ||
        filters.category_ids.length > 0 ||
        filters.breed_ids.length > 0 ||
        filters.listing_types.length > 0
        ? 'Listings matching your filters.'
        : 'Every listing, newest first.';
});
</script>

<template>
    <div class="mx-auto w-full max-w-7xl space-y-6 px-4 py-8 sm:px-6">
        <Head title="Home" />

        <!-- `Heading` renders an h2, so the page still owes the document an h1. -->
        <h1 class="sr-only">{{ heading }}</h1>

        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading :title="heading" :description="description" />

            <div class="flex flex-wrap items-start gap-2">
                <NearbySearchButton
                    :nearby="nearby"
                    :radius="radius"
                    :bounds="filterBounds"
                />
                <PetFilterSheet
                    :filters="filters"
                    :bounds="filterBounds"
                    :listing-types="listingTypes"
                    :categories="categories"
                />
            </div>
        </div>

        <PetFeed :pets="pets" />
    </div>
</template>
