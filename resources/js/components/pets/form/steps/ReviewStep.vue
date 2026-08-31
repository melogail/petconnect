<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DetailList from '@/components/DetailList.vue';
import type { DetailItem } from '@/components/DetailList.vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import type { PetFormState } from '@/lib/petForm';
import type { PetFormOptions, SelectOption } from '@/types';

/**
 * Everything the form is about to post, read back.
 *
 * The point of the step is that a listing is long and its fields are spread
 * over seven screens; this is the only place all of them are visible at once.
 * It renders the *form state*, not the payload — the payload's nulls and
 * dropped rows are the save's business, and showing them here would read as
 * data loss rather than as normalisation.
 */
const {
    form,
    options,
    currentFeaturedUrl = null,
} = defineProps<{
    form: InertiaForm<PetFormState>;
    options: PetFormOptions;
    currentFeaturedUrl?: string | null;
}>();

function label<T extends string>(
    list: SelectOption<T>[],
    value: string,
): string | null {
    return list.find((option) => option.value === value)?.label ?? null;
}

const coverPreview = ref<string | null>(null);

watch(
    () => form.featuredImage,
    (file) => {
        if (coverPreview.value !== null) {
            URL.revokeObjectURL(coverPreview.value);
        }

        coverPreview.value = file === null ? null : URL.createObjectURL(file);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (coverPreview.value !== null) {
        URL.revokeObjectURL(coverPreview.value);
    }
});

const cover = computed(() => coverPreview.value ?? currentFeaturedUrl);

const basics = computed<DetailItem[]>(() => [
    { label: 'Name', value: form.name },
    {
        label: 'Category',
        value:
            options.categories.find(
                (category) => category.id === form.category_id,
            )?.name ?? null,
    },
    {
        label: 'Breed',
        value:
            options.categories
                .flatMap((category) => category.breeds ?? [])
                .find((breed) => breed.id === form.breed_id)?.name ?? null,
    },
    { label: 'Age', value: form.age === '' ? null : `${form.age} years` },
    { label: 'Gender', value: label(options.genders, form.gender) },
    { label: 'Colour', value: form.color },
    { label: 'Weight', value: form.weight === '' ? null : `${form.weight} kg` },
    {
        label: 'Listing type',
        value: label(options.listingTypes, form.listing_type),
    },
    { label: 'Price', value: form.price },
    { label: 'Status', value: label(options.statuses, form.status) },
]);

const location = computed<DetailItem[]>(() => [
    { label: 'City', value: form.location.city },
    { label: 'State', value: form.location.state },
    { label: 'Country', value: form.location.country },
    { label: 'Postal code', value: form.location.postalCode },
    { label: 'Address', value: form.location.address },
    { label: 'Building detail', value: form.location.detailedAddress },
    {
        label: 'Map pin',
        value:
            form.location.lat === '' || form.location.lng === ''
                ? null
                : `${form.location.lat}, ${form.location.lng}`,
    },
]);

const health = computed<DetailItem[]>(() => [
    {
        label: 'Health status',
        value: label(options.healthStatuses, form.health.status),
    },
    { label: 'Vaccinated', value: form.health.vaccinated ? 'Yes' : 'No' },
    {
        label: 'Spayed / neutered',
        value: form.health.spayedNeutered ? 'Yes' : 'No',
    },
    { label: 'Last vet visit', value: form.health.lastVetVisit },
    { label: 'Special needs', value: form.health.specialNeeds },
    { label: 'Veterinarian', value: form.health.vetName },
    { label: 'Vet phone', value: form.health.vetPhone },
]);

const records = computed<DetailItem[]>(() => [
    {
        label: 'Vaccinations',
        value:
            form.health.vaccinations
                .filter((row) => row.name.trim() !== '')
                .map((row) => row.name)
                .join(', ') || null,
    },
    {
        label: 'Medications',
        value:
            form.health.medications
                .filter((row) => row.name.trim() !== '')
                .map((row) => row.name)
                .join(', ') || null,
    },
    { label: 'Allergies', value: form.health.allergies.join(', ') || null },
]);

const extras = computed<DetailItem[]>(() =>
    form.additionalInfo
        .filter((row) => row.label.trim() !== '' && row.value.trim() !== '')
        .map((row) => ({ label: row.label, value: row.value })),
);
</script>

<template>
    <div class="space-y-6">
        <section class="space-y-3">
            <h3 class="font-medium">Photos</h3>
            <div class="flex flex-wrap items-center gap-4">
                <img
                    v-if="cover"
                    :src="cover"
                    alt="Cover photo"
                    class="bg-muted size-28 rounded-lg object-cover"
                />
                <p v-else class="text-muted-foreground text-sm">
                    No cover photo picked yet.
                </p>
                <Badge variant="secondary">
                    {{ form.images.length }} new gallery photo(s)
                </Badge>
                <Badge v-if="form.deletedMediaIds.length > 0" variant="outline">
                    {{ form.deletedMediaIds.length }} to remove
                </Badge>
            </div>
        </section>

        <Separator />

        <section class="space-y-3">
            <h3 class="font-medium">Basics</h3>
            <DetailList :items="basics" />
        </section>

        <Separator />

        <section class="space-y-3">
            <h3 class="font-medium">Location</h3>
            <DetailList :items="location" />
        </section>

        <Separator />

        <section class="space-y-3">
            <h3 class="font-medium">Description</h3>
            <p
                v-if="form.description"
                class="text-sm leading-relaxed whitespace-pre-line"
            >
                {{ form.description }}
            </p>
            <p v-else class="text-muted-foreground text-sm">
                Nothing written yet.
            </p>
            <div v-if="form.traits.length > 0" class="flex flex-wrap gap-2">
                <Badge
                    v-for="trait in form.traits"
                    :key="trait"
                    variant="secondary"
                >
                    {{ trait }}
                </Badge>
            </div>
        </section>

        <Separator />

        <section class="space-y-3">
            <h3 class="font-medium">Health</h3>
            <DetailList :items="health" />
            <DetailList :items="records" />
        </section>

        <template v-if="extras.length > 0">
            <Separator />
            <section class="space-y-3">
                <h3 class="font-medium">Anything else</h3>
                <DetailList :items="extras" />
            </section>
        </template>
    </div>
</template>
