<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import PetFormWizard from '@/components/pets/form/PetFormWizard.vue';
import { blankPetForm, toPetPayload, type PetFormState } from '@/lib/petForm';
import { home } from '@/routes';
import { create, store as storePet } from '@/routes/pets';
import type {
    PetCategoryOption,
    PetFormOptions,
    PetGender,
    PetHealthStatus,
    PetListingType,
    PetPhotoBounds,
    PetStatus,
    SelectOption,
} from '@/types';

/**
 * Publish a listing.
 *
 * The page owns the form object and the route; `PetFormWizard` owns the eight
 * steps and the navigation between them. That split is what lets create and
 * edit share every field without either one guessing where it is posting.
 *
 * `toPetPayload` is the single boundary between the shape the inputs bind to
 * and the shape `App\Concerns\PetValidationRules` accepts — including the rule
 * that every empty collection goes out as `null` rather than `[]`, because a
 * create always carries a `featuredImage` file and is therefore always
 * multipart, where `[]` serialises to nothing at all.
 */
const props = defineProps<{
    categories: PetCategoryOption[];
    listingTypes: SelectOption<PetListingType>[];
    statuses: SelectOption<PetStatus>[];
    genders: SelectOption<PetGender>[];
    healthStatuses: SelectOption<PetHealthStatus>[];
    photoBounds: PetPhotoBounds;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Home', href: home() },
            { title: 'Publish a listing', href: create() },
        ],
    },
});

const options = computed<PetFormOptions>(() => ({
    categories: props.categories,
    listingTypes: props.listingTypes,
    statuses: props.statuses,
    genders: props.genders,
    healthStatuses: props.healthStatuses,
}));

const form = useForm<PetFormState>(
    blankPetForm(props.statuses[0]?.value ?? ''),
);

function submit(): void {
    form.transform(toPetPayload).post(storePet().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6">
        <Head title="Publish a listing" />

        <!-- `Heading` renders an h2, so the page still owes the document an h1. -->
        <h1 class="sr-only">Publish a listing</h1>

        <Heading
            title="Publish a listing"
            description="Eight short steps. Nothing is saved until the last one."
        />

        <PetFormWizard
            :form="form"
            :options="options"
            :photo-bounds="photoBounds"
            submit-label="Publish listing"
            @submit="submit"
        />
    </div>
</template>
