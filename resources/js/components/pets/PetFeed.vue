<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { PawPrint } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PetListingCard from '@/components/pets/PetListingCard.vue';
import { Spinner } from '@/components/ui/spinner';
import type { Paginated, PetCard } from '@/types';

/**
 * The discovery grid.
 *
 * `pets` ships from `Inertia::scroll()`, so the payload carries
 * `mergeProps: ["pets.data"]` and the cursor metadata `<InfiniteScroll>` reads.
 * The component owns the whole append cycle — no `router.reload` handler here,
 * and no page-number state: a manual reload would replace `pets.data` instead
 * of merging into it and leave the visitor looking at page 2 alone.
 */
defineProps<{
    pets: Paginated<PetCard>;
}>();
</script>

<template>
    <EmptyState
        v-if="pets.data.length === 0"
        :icon="PawPrint"
        title="No listings match these filters"
        description="Widen the filters, or clear them to see everything."
    />

    <InfiniteScroll
        v-else
        data="pets"
        as="div"
        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
    >
        <PetListingCard v-for="pet in pets.data" :key="pet.id" :pet="pet" />

        <!-- Rendered in the component's own trigger element, outside the grid. -->
        <template #loading>
            <p
                class="text-muted-foreground flex items-center justify-center gap-2 py-6 text-sm"
            >
                <Spinner />
                Loading more listings…
            </p>
        </template>
    </InfiniteScroll>
</template>
