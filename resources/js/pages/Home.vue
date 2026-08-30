<script setup lang="ts">
// Placeholder page — proves the Home props arrive. Replaced in Phase 4.
import { Deferred, Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { login, register } from '@/routes';
import { create as createPet, show as showPet } from '@/routes/pets';
import type {
    HomeFeedBounds,
    HomeFeedFilters,
    Paginated,
    PetCard,
    PetCategoryOption,
    PetListingType,
    SelectOption,
} from '@/types';

defineProps<{
    /**
     * Sent with Inertia::scroll() — a merge prop, so <InfiniteScroll> appends
     * each page into `pets.data` instead of replacing it.
     */
    pets: Paginated<PetCard>;
    filters: HomeFeedFilters;
    nearby: boolean;
    radius: number | null;
    listingTypes: SelectOption<PetListingType>[];
    filterBounds: HomeFeedBounds;
    /**
     * Sent with Inertia::defer() — missing from the initial page object and
     * announced in `deferredProps`, so Inertia fetches it by itself in one
     * follow-up partial reload. The page must not reload it as well, and the
     * prop stays `undefined` until that request lands.
     */
    categories?: PetCategoryOption[];
}>();
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-6 p-6">
        <Head title="Home" />

        <header class="space-y-1">
            <h1 class="text-2xl font-semibold">Home</h1>
            <p class="text-muted-foreground text-sm">
                Placeholder feed — the real UI lands in Phase 4.
            </p>
            <nav class="flex gap-3 text-sm underline">
                <Link :href="createPet()">Publish a listing</Link>
                <Link v-if="!$page.props.auth.user" :href="login()"
                    >Log in</Link
                >
                <Link v-if="!$page.props.auth.user" :href="register()">
                    Register
                </Link>
            </nav>
        </header>

        <section class="space-y-2">
            <h2 class="font-medium">
                Pets ({{ pets.data.length }} of {{ pets.meta.total }} loaded)
            </h2>
            <p
                v-if="pets.data.length === 0"
                class="text-muted-foreground text-sm"
            >
                No listings match these filters.
            </p>
            <InfiniteScroll
                v-else
                data="pets"
                as="ul"
                class="space-y-1 text-sm"
            >
                <li v-for="pet in pets.data" :key="pet.id">
                    <Link :href="showPet(pet.id)" class="underline">
                        {{ pet.name }}
                    </Link>
                    <span class="text-muted-foreground">
                        — {{ pet.listing_type }} / {{ pet.status
                        }}<template v-if="pet.category">
                            / {{ pet.category.name }}</template
                        ><template v-if="pet.distance !== undefined">
                            / {{ pet.distance }} km</template
                        >
                    </span>
                </li>

                <!-- Rendered in the component's own trigger div, outside the <ul>. -->
                <template #loading>
                    <p
                        class="text-muted-foreground flex items-center gap-2 py-2 text-sm"
                    >
                        <Spinner />
                        Loading more listings…
                    </p>
                </template>
            </InfiniteScroll>
        </section>

        <section class="space-y-2">
            <h2 class="font-medium">Categories</h2>
            <!--
                The router fetches `categories` on its own from the
                `deferredProps` announcement — one partial reload per group.
                <Deferred> only gates the markup on it, so the page issues no
                reload of its own.
            -->
            <Deferred data="categories">
                <template #fallback>
                    <div class="space-y-2">
                        <Skeleton class="h-4 w-48" />
                        <Skeleton class="h-4 w-32" />
                        <Skeleton class="h-4 w-40" />
                    </div>
                </template>

                <p
                    v-if="!categories?.length"
                    class="text-muted-foreground text-sm"
                >
                    No categories yet.
                </p>
                <ul v-else class="text-sm">
                    <li v-for="category in categories" :key="category.id">
                        {{ category.name }}
                    </li>
                </ul>
            </Deferred>
        </section>

        <section class="space-y-2 text-sm">
            <h2 class="font-medium">Context</h2>
            <p>
                Listing types: {{ listingTypes.map((t) => t.label).join(', ') }}
            </p>
            <p>Nearby: {{ nearby ? `yes, ${radius} km` : 'no' }}</p>
            <pre class="bg-muted overflow-x-auto rounded p-3 text-xs">{{
                { filters, filterBounds }
            }}</pre>
        </section>
    </div>
</template>
