<script setup lang="ts">
// Placeholder page — proves the edit-form props arrive. Replaced in Phase 4.
import { Head, Link } from '@inertiajs/vue3';
import { show } from '@/routes/pets';
import type { PetCategoryOption, PetDetail, SelectOption } from '@/types';

const props = defineProps<{
    pet: PetDetail;
    categories: PetCategoryOption[];
    listingTypes: SelectOption[];
    statuses: SelectOption[];
}>();
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-6 p-6">
        <Head :title="`Edit ${props.pet.name}`" />

        <header class="space-y-1">
            <h1 class="text-2xl font-semibold">Edit {{ pet.name }}</h1>
            <p class="text-muted-foreground text-sm">
                Placeholder form page — the real form lands in Phase 4.
            </p>
            <Link :href="show(pet.id)" class="text-sm underline">
                Back to listing
            </Link>
        </header>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <dt>Listing type</dt>
            <dd>{{ pet.listing_type }}</dd>
            <dt>Status</dt>
            <dd>{{ pet.status }}</dd>
            <dt>Category</dt>
            <dd>{{ pet.category?.name ?? '—' }}</dd>
            <dt>Address</dt>
            <dd>{{ pet.location.address ?? '—' }}</dd>
            <dt>Vet</dt>
            <dd>{{ pet.health.vet_name ?? '—' }}</dd>
        </dl>

        <section class="space-y-1 text-sm">
            <h2 class="font-medium">Options</h2>
            <p>Categories: {{ categories.length }}</p>
            <p>
                Listing types: {{ listingTypes.map((t) => t.label).join(', ') }}
            </p>
            <p>Statuses: {{ statuses.map((s) => s.label).join(', ') }}</p>
        </section>
    </div>
</template>
