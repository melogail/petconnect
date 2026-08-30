<script setup lang="ts">
// Placeholder page — proves the pet detail props arrive. Replaced in Phase 4.
import { Head, Link } from '@inertiajs/vue3';
import { home } from '@/routes';
import { edit as editPet } from '@/routes/pets';
import type { PetDetail } from '@/types';

defineProps<{
    pet: PetDetail;
}>();
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-6 p-6">
        <Head :title="pet.name" />

        <header class="space-y-1">
            <h1 class="text-2xl font-semibold">{{ pet.name }}</h1>
            <p class="text-muted-foreground text-sm">
                Placeholder listing page — the real UI lands in Phase 4.
            </p>
            <nav class="flex gap-3 text-sm underline">
                <Link :href="home()">Home</Link>
                <Link v-if="pet.is_owner" :href="editPet(pet.id)">Edit</Link>
            </nav>
        </header>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <dt>Listing type</dt>
            <dd>{{ pet.listing_type }}</dd>
            <dt>Status</dt>
            <dd>{{ pet.status }}</dd>
            <dt>Category</dt>
            <dd>{{ pet.category?.name ?? '—' }}</dd>
            <dt>Breed</dt>
            <dd>{{ pet.breed?.name ?? '—' }}</dd>
            <dt>Owner</dt>
            <dd>{{ pet.user?.name ?? '—' }}</dd>
            <dt>City</dt>
            <dd>{{ pet.location.city ?? '—' }}</dd>
            <dt>Views / likes / comments</dt>
            <dd>
                {{ pet.views }} / {{ pet.likes_count }} /
                {{ pet.comments_count }}
            </dd>
            <dt>Images</dt>
            <dd>{{ pet.images?.length ?? 0 }}</dd>
        </dl>

        <!-- Owner-only leaves are absent, not null, for everybody else. -->
        <section v-if="pet.is_owner" class="space-y-1 text-sm">
            <h2 class="font-medium">Owner-only</h2>
            <p>Address: {{ pet.location.address ?? '—' }}</p>
            <p>
                Coordinates:
                {{ pet.location.coordinates?.lat ?? '—' }},
                {{ pet.location.coordinates?.lng ?? '—' }}
            </p>
            <p>Vet: {{ pet.health.vet_name ?? '—' }}</p>
        </section>
    </div>
</template>
