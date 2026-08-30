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

        <!-- Top-level scalars are snake_case; the nested groups are camelCase. -->
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <dt>Listing type</dt>
            <dd>{{ pet.listing_type }}</dd>
            <dt>Status</dt>
            <dd>{{ pet.status }}</dd>
            <dt>Gender</dt>
            <dd>{{ pet.gender }}</dd>
            <dt>Category</dt>
            <dd>{{ pet.category?.name ?? '—' }}</dd>
            <dt>Breed</dt>
            <dd>{{ pet.breed?.name ?? '—' }}</dd>
            <dt>Owner</dt>
            <dd>{{ pet.user?.name ?? '—' }}</dd>
            <dt>City</dt>
            <dd>{{ pet.location.city }}</dd>
            <dt>Postal code</dt>
            <dd>{{ pet.location.postalCode ?? '—' }}</dd>
            <dt>Health</dt>
            <dd>{{ pet.health.status }}</dd>
            <dt>Spayed / neutered</dt>
            <dd>{{ pet.health.spayedNeutered ? 'yes' : 'no' }}</dd>
            <dt>Special needs</dt>
            <dd>{{ pet.health.specialNeeds ?? '—' }}</dd>
            <dt>Last vet visit</dt>
            <dd>{{ pet.health.lastVetVisit ?? '—' }}</dd>
            <dt>Traits</dt>
            <dd>{{ pet.traits?.join(', ') ?? '—' }}</dd>
            <dt>Additional info</dt>
            <dd>{{ Object.keys(pet.additionalInfo ?? {}).length }} entries</dd>
            <dt>Views / likes / comments</dt>
            <dd>
                {{ pet.views }} / {{ pet.likes_count }} /
                {{ pet.comments_count }}
            </dd>
            <dt>Photos</dt>
            <dd>{{ pet.photos?.length ?? 0 }}</dd>
        </dl>

        <!--
            The thread is bounded: at most 20 top-level comments, each with at
            most 3 replies. `comments_count` is the true total.
        -->
        <section class="space-y-1 text-sm">
            <h2 class="font-medium">
                Comments ({{ pet.comments?.length ?? 0 }} of
                {{ pet.comments_count }} loaded)
            </h2>
            <ul>
                <li v-for="comment in pet.comments" :key="comment.id">
                    {{ comment.user?.name ?? 'Someone' }} —
                    {{ comment.replies?.length ?? 0 }} replies
                </li>
            </ul>
        </section>

        <!-- Owner-only leaves are absent, not null, for everybody else. -->
        <section v-if="pet.is_owner" class="space-y-1 text-sm">
            <h2 class="font-medium">Owner-only</h2>
            <p>Address: {{ pet.location.address ?? '—' }}</p>
            <p>Detailed address: {{ pet.location.detailedAddress ?? '—' }}</p>
            <p>
                Coordinates:
                {{ pet.location.coordinates?.lat ?? '—' }},
                {{ pet.location.coordinates?.lng ?? '—' }}
            </p>
            <p>Vet: {{ pet.health.vetName ?? '—' }}</p>
            <p>Vet phone: {{ pet.health.vetPhone ?? '—' }}</p>
            <p>Medications: {{ pet.health.medications?.length ?? 0 }}</p>
            <p>Allergies: {{ pet.health.allergies?.join(', ') ?? '—' }}</p>
        </section>
    </div>
</template>
