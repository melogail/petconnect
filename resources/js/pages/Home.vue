<script setup lang="ts">
// Placeholder page — proves the Home props arrive. Replaced in Phase 4.
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { login, register } from '@/routes';
import { create as createPet, show as showPet } from '@/routes/pets';
import type {
    HomeFeedBounds,
    HomeFeedFilters,
    Paginated,
    PetCard,
    PetCategoryOption,
    SelectOption,
} from '@/types';

const props = defineProps<{
    pets: Paginated<PetCard>;
    filters: HomeFeedFilters;
    nearby: boolean;
    radius: number | null;
    listingTypes: SelectOption[];
    filterBounds: HomeFeedBounds;
    /** Sent with Inertia::optional() — absent until it is asked for. */
    categories?: PetCategoryOption[];
}>();

// `categories` is optional, not deferred, so nothing fetches it automatically.
onMounted(() => {
    if (props.categories === undefined) {
        router.reload({ only: ['categories'] });
    }
});
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
                Pets ({{ pets.meta.total }} total, page
                {{ pets.meta.current_page }} of {{ pets.meta.last_page }})
            </h2>
            <p
                v-if="pets.data.length === 0"
                class="text-muted-foreground text-sm"
            >
                No listings match these filters.
            </p>
            <ul v-else class="space-y-1 text-sm">
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
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="font-medium">Categories</h2>
            <div v-if="categories === undefined" class="space-y-2">
                <Skeleton class="h-4 w-48" />
                <Skeleton class="h-4 w-32" />
                <Skeleton class="h-4 w-40" />
            </div>
            <p
                v-else-if="categories.length === 0"
                class="text-muted-foreground text-sm"
            >
                No categories yet.
            </p>
            <ul v-else class="text-sm">
                <li v-for="category in categories" :key="category.id">
                    {{ category.name }}
                </li>
            </ul>
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
