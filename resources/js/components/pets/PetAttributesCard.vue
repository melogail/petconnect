<script setup lang="ts">
import { PawPrint } from '@lucide/vue';
import { computed } from 'vue';
import DetailList from '@/components/DetailList.vue';
import type { DetailItem } from '@/components/DetailList.vue';
import PetPanel from '@/components/pets/PetPanel.vue';
import { useLocale } from '@/composables/useLocale';
import { taxonomyName } from '@/lib/taxonomy';
import type { PetDetail } from '@/types';

/**
 * The listing's physical facts.
 *
 * `age` is a varchar column, so it arrives as a string even though it reads
 * numeric; `weight` is a real number and is left unformatted because the unit
 * is not part of the payload.
 *
 * The category and breed *values* go through `taxonomyName`, never `.name`:
 * both resources ship `name` and `name_ar` on every row so the client can pick
 * one per locale, and reading `.name` here showed Arabic readers English
 * taxonomy names. `locale.current` is the language, not `locale.direction`.
 *
 * The row *labels* are still hardcoded English, and so are `years`, `kg` and
 * the panel title. `lang/{en,ar}.json` has no `Category` label at all and only
 * a `wizard.breed` — a key belonging to the pet form's namespace — so
 * translating half of this pair would be worse than translating none. Inventing
 * keys is this file's later phase's job, not this change's.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const { locale } = useLocale();

const items = computed<DetailItem[]>(() => [
    {
        label: 'Category',
        value: pet.category
            ? taxonomyName(pet.category, locale.value.current)
            : null,
    },
    {
        label: 'Breed',
        value: pet.breed ? taxonomyName(pet.breed, locale.value.current) : null,
    },
    { label: 'Age', value: pet.age === '' ? null : `${pet.age} years` },
    { label: 'Gender', value: pet.gender },
    { label: 'Colour', value: pet.color },
    { label: 'Weight', value: pet.weight === null ? null : `${pet.weight} kg` },
]);
</script>

<template>
    <PetPanel title="Details" :icon="PawPrint">
        <DetailList :items="items" />
    </PetPanel>
</template>
