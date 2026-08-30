<script setup lang="ts">
// Placeholder page — proves the create-form options arrive. Replaced in Phase 4.
import { Head } from '@inertiajs/vue3';
import type { PetCategoryOption, SelectOption } from '@/types';
import { home } from '@/routes';
import { create } from '@/routes/pets';

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
    listingTypes: SelectOption[];
    statuses: SelectOption[];
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

        <section class="space-y-1 text-sm">
            <h2 class="font-medium">Listing types</h2>
            <p>{{ listingTypes.map((type) => type.label).join(', ') }}</p>
            <h2 class="font-medium">Statuses</h2>
            <p>{{ statuses.map((status) => status.label).join(', ') }}</p>
        </section>
    </div>
</template>
