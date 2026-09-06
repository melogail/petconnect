<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import PetFormWizard from '@/components/pets/form/PetFormWizard.vue';
import { Button } from '@/components/ui/button';
import {
    petFormFromDetail,
    toPetPayload,
    type PetFormState,
} from '@/lib/petForm';
import { show as showPet, update as updatePet } from '@/routes/pets';
import type {
    PetCategoryOption,
    PetDetail,
    PetFormOptions,
    PetGender,
    PetHealthStatus,
    PetListingType,
    PetPhotoBounds,
    PetStatus,
    SelectOption,
} from '@/types';

/**
 * Edit a listing.
 *
 * ## Why this posts with `update.form()` rather than `form.put()`
 *
 * `pets.update` is a PUT, and PHP populates neither `$_POST` nor `$_FILES` for
 * a PUT body — so a real PUT carrying a new photo would arrive with no photo
 * and no fields at all. Inertia does not spoof the method for you. Wayfinder's
 * form variant is exactly this workaround: `update.form(id).action` is the
 * listing's URL with `?_method=PUT`, posted as POST and turned back into a PUT
 * by Symfony's method override. It is the same mechanism `<Form>` uses, reached
 * through `useForm` because this form's payload is nested, repeated and needs a
 * transform.
 *
 * ## Why the payload is built rather than echoed
 *
 * A PUT here is a **full replacement**: the pipeline writes a value for every
 * column the form owns, so an omitted key is written as null. `toPetPayload()`
 * therefore always emits every `present` scalar — `breed_id`, `weight`,
 * `price`, the three optional `location` strings and all seven `health`
 * scalars — null-valued when empty, while the collection keys go out as `null`
 * when empty because `present` on them is not expressible over multipart.
 *
 * And the read payload cannot be posted back as-is: `category`/`breed` are
 * objects behind `category_id`/`breed_id`, `featured_image` is a URL behind the
 * `featuredImage` upload, and `photos` are media rows while the write side's
 * `images` are Files. `petFormFromDetail()` is where those four translations
 * happen.
 */
const props = defineProps<{
    pet: PetDetail;
    categories: PetCategoryOption[];
    listingTypes: SelectOption<PetListingType>[];
    statuses: SelectOption<PetStatus>[];
    genders: SelectOption<PetGender>[];
    healthStatuses: SelectOption<PetHealthStatus>[];
    photoBounds: PetPhotoBounds;
}>();

const options = computed<PetFormOptions>(() => ({
    categories: props.categories,
    listingTypes: props.listingTypes,
    statuses: props.statuses,
    genders: props.genders,
    healthStatuses: props.healthStatuses,
}));

const form = useForm<PetFormState>(petFormFromDetail(props.pet));

function submit(): void {
    form.transform(toPetPayload).post(updatePet.form(props.pet.id).action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="mx-auto w-full max-w-4xl space-y-6 px-4 py-8 sm:px-6">
        <Head :title="`Edit ${pet.name}`" />

        <!-- `Heading` renders an h2, so the page still owes the document an h1. -->
        <h1 class="sr-only">Edit {{ pet.name }}</h1>

        <div class="flex flex-wrap items-end justify-between gap-3">
            <Heading
                :title="`Edit ${pet.name}`"
                description="Saving replaces the whole listing, so every field is posted."
            />
            <Button as-child variant="outline">
                <Link :href="showPet(pet.id)">Back to listing</Link>
            </Button>
        </div>

        <PetFormWizard
            :form="form"
            :options="options"
            :photo-bounds="photoBounds"
            :photos="pet.photos"
            :current-featured-url="pet.featured_image"
            submit-label="Save changes"
            @submit="submit"
        />
    </div>
</template>
