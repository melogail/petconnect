<script setup lang="ts">
// Placeholder page — proves the create-form options arrive. Replaced in Phase 4.
import { Head } from '@inertiajs/vue3';
import { home } from '@/routes';
import { create } from '@/routes/pets';
import type {
    PetCategoryOption,
    PetGender,
    PetHealthStatus,
    PetListingType,
    PetStatus,
    SelectOption,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Home', href: home() },
            { title: 'Publish a listing', href: create() },
        ],
    },
});

defineProps<{
    categories: PetCategoryOption[];
    listingTypes: SelectOption<PetListingType>[];
    statuses: SelectOption<PetStatus>[];
    genders: SelectOption<PetGender>[];
    healthStatuses: SelectOption<PetHealthStatus>[];
}>();
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-6 p-6">
        <Head title="Publish a listing" />

        <header class="space-y-1">
            <h1 class="text-2xl font-semibold">Publish a listing</h1>
            <p class="text-muted-foreground text-sm">
                Placeholder form page — the real form lands in Phase 4.
            </p>
        </header>

        <section class="space-y-1 text-sm">
            <h2 class="font-medium">Categories ({{ categories.length }})</h2>
            <ul>
                <li v-for="category in categories" :key="category.id">
                    {{ category.name }} ({{ category.breeds?.length ?? 0 }}
                    breeds)
                </li>
            </ul>
        </section>

        <!-- The form posts category_id / breed_id, not the objects above. -->
        <section class="space-y-1 text-sm">
            <h2 class="font-medium">Options</h2>
            <p>
                Listing types: {{ listingTypes.map((o) => o.label).join(', ') }}
            </p>
            <p>Statuses: {{ statuses.map((o) => o.label).join(', ') }}</p>
            <p>Genders: {{ genders.map((o) => o.label).join(', ') }}</p>
            <p>
                Health statuses:
                {{ healthStatuses.map((o) => o.label).join(', ') }}
            </p>
        </section>
    </div>
</template>
