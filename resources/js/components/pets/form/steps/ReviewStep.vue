<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DetailList from '@/components/DetailList.vue';
import type { DetailItem } from '@/components/DetailList.vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { type InputValue, trimmedInput } from '@/lib/inputValue';
import type { PetFormState } from '@/lib/petForm';
import { taxonomyName } from '@/lib/taxonomy';
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

const { t } = useTranslations();
const { locale } = useLocale();

function label<T extends string>(
    list: SelectOption<T>[],
    value: string,
): string | null {
    return list.find((option) => option.value === value)?.label ?? null;
}

/**
 * A numeric-ish box read back, or null when it is blank.
 *
 * `quantity`, the sex counts, `weight` and `price` are `InputValue` — a
 * `v-model` is free to write a `number` into any of them — so they are coerced
 * here rather than compared against `''` and interpolated raw.
 */
function unit(value: InputValue): string | null {
    const text = trimmedInput(value);

    return text === '' ? null : text;
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

/** The pin, or null unless both boxes hold something. */
const pin = computed(() => {
    const lat = trimmedInput(form.location.lat);
    const lng = trimmedInput(form.location.lng);

    return lat === '' || lng === '' ? null : `${lat}, ${lng}`;
});

const yesNo = (value: boolean): string => t(value ? 'common.yes' : 'common.no');

const basics = computed<DetailItem[]>(() => {
    const category = options.categories.find(
        (entry) => entry.id === form.category_id,
    );
    const breed = options.categories
        .flatMap((entry) => entry.breeds ?? [])
        .find((entry) => entry.id === form.breed_id);
    const weight = unit(form.weight);

    return [
        { label: t('wizard.name'), value: form.name },
        {
            label: t('wizard.category'),
            value: category
                ? taxonomyName(category, locale.value.current)
                : null,
        },
        {
            label: t('wizard.breed'),
            value: breed ? taxonomyName(breed, locale.value.current) : null,
        },
        { label: t('wizard.birth_date'), value: unit(form.birth_date) },
        {
            label: t('wizard.gender'),
            value: label(options.genders, form.gender),
        },
        { label: t('wizard.quantity'), value: unit(form.quantity) },
        {
            label: t('wizard.males_females'),
            value:
                Number(form.quantity) > 1
                    ? `${unit(form.male_count) ?? '0'} / ${unit(form.female_count) ?? '0'}`
                    : null,
        },
        { label: t('wizard.colour'), value: form.color },
        {
            label: t('pets.weight'),
            value:
                weight === null ? null : t('pets.weight_kg', { value: weight }),
        },
        {
            label: t('wizard.listing_type'),
            value: label(options.listingTypes, form.listing_type),
        },
        { label: t('pets.price'), value: unit(form.price) },
        {
            label: t('wizard.status'),
            value: label(options.statuses, form.status),
        },
    ];
});

const location = computed<DetailItem[]>(() => [
    { label: t('wizard.city'), value: form.location.city },
    { label: t('wizard.state_or_province'), value: form.location.state },
    { label: t('wizard.country'), value: form.location.country },
    { label: t('wizard.postal_code'), value: form.location.postalCode },
    { label: t('wizard.street_address'), value: form.location.address },
    {
        label: t('wizard.building_detail'),
        value: form.location.detailedAddress,
    },
    { label: t('wizard.map_pin'), value: pin.value },
]);

const health = computed<DetailItem[]>(() => [
    {
        label: t('wizard.health_status'),
        value: label(options.healthStatuses, form.health.status),
    },
    { label: t('wizard.vaccinated'), value: yesNo(form.health.vaccinated) },
    {
        label: t('wizard.spayed_neutered'),
        value: yesNo(form.health.spayedNeutered),
    },
    { label: t('wizard.last_vet_visit'), value: form.health.lastVetVisit },
    { label: t('pets.special_needs'), value: form.health.specialNeeds },
    { label: t('wizard.veterinarian'), value: form.health.vetName },
    { label: t('pets.vet_phone'), value: form.health.vetPhone },
]);

const records = computed<DetailItem[]>(() => [
    {
        label: t('pets.vaccinations'),
        value:
            form.health.vaccinations
                .filter((row) => trimmedInput(row.name) !== '')
                .map((row) => row.name)
                .join(', ') || null,
    },
    {
        label: t('pets.medications'),
        value:
            form.health.medications
                .filter((row) => trimmedInput(row.name) !== '')
                .map((row) => row.name)
                .join(', ') || null,
    },
    {
        label: t('wizard.allergies'),
        value: form.health.allergies.join(', ') || null,
    },
]);

const extras = computed<DetailItem[]>(() =>
    form.additionalInfo
        .filter(
            (row) =>
                trimmedInput(row.label) !== '' &&
                trimmedInput(row.value) !== '',
        )
        .map((row) => ({ label: row.label, value: row.value })),
);
</script>

<template>
    <div class="space-y-6">
        <section class="space-y-3">
            <h3 class="font-medium">{{ t('wizard.step_photos') }}</h3>
            <div class="flex flex-wrap items-center gap-4">
                <img
                    v-if="cover"
                    :src="cover"
                    :alt="t('wizard.cover_photo')"
                    class="bg-muted size-28 rounded-lg object-cover"
                />
                <p v-else class="text-muted-foreground text-sm">
                    {{ t('wizard.no_cover_picked') }}
                </p>
                <Badge variant="secondary">
                    {{
                        t('wizard.new_gallery_photos', {
                            count: form.images.length,
                        })
                    }}
                </Badge>
                <Badge v-if="form.deletedMediaIds.length > 0" variant="outline">
                    {{
                        t('wizard.photos_to_remove', {
                            count: form.deletedMediaIds.length,
                        })
                    }}
                </Badge>
            </div>
        </section>

        <Separator />

        <section class="space-y-3">
            <h3 class="font-medium">{{ t('wizard.step_basics') }}</h3>
            <DetailList :items="basics" />
        </section>

        <Separator />

        <section class="space-y-3">
            <h3 class="font-medium">{{ t('wizard.step_location') }}</h3>
            <DetailList :items="location" />
        </section>

        <Separator />

        <section class="space-y-3">
            <h3 class="font-medium">{{ t('wizard.description') }}</h3>
            <p
                v-if="form.description"
                class="text-sm leading-relaxed whitespace-pre-line"
            >
                {{ form.description }}
            </p>
            <p v-else class="text-muted-foreground text-sm">
                {{ t('wizard.nothing_written_yet') }}
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
            <h3 class="font-medium">{{ t('wizard.step_health') }}</h3>
            <DetailList :items="health" />
            <DetailList :items="records" />
        </section>

        <template v-if="extras.length > 0">
            <Separator />
            <section class="space-y-3">
                <h3 class="font-medium">{{ t('wizard.anything_else') }}</h3>
                <DetailList :items="extras" />
            </section>
        </template>
    </div>
</template>
