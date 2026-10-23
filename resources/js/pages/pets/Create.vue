<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import PetFormWizard from '@/components/pets/form/PetFormWizard.vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    blankPetForm,
    localisedPetFormOptions,
    toPetPayload,
    type PetFormState,
} from '@/lib/petForm';
import { store as storePet } from '@/routes/pets';
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

const { t } = useTranslations();

const options = computed<PetFormOptions>(() =>
    localisedPetFormOptions(
        {
            categories: props.categories,
            listingTypes: props.listingTypes,
            statuses: props.statuses,
            genders: props.genders,
            healthStatuses: props.healthStatuses,
        },
        t,
    ),
);

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
    <div class="mx-auto w-full max-w-4xl space-y-6 px-4 py-8 sm:px-6">
        <Head :title="t('wizard.publish_listing')" />

        <!-- `Heading` renders an h2, so the page still owes the document an h1. -->
        <h1 class="sr-only">{{ t('wizard.publish_listing') }}</h1>

        <Heading
            :title="t('wizard.publish_listing')"
            :description="t('wizard.publish_listing_desc')"
        />

        <PetFormWizard
            :form="form"
            :options="options"
            :photo-bounds="photoBounds"
            :submit-label="t('wizard.publish_listing_button')"
            @submit="submit"
        />
    </div>
</template>
