<script setup lang="ts">
import { PawPrint } from '@lucide/vue';
import { computed } from 'vue';
import DetailList from '@/components/DetailList.vue';
import type { DetailItem } from '@/components/DetailList.vue';
import PetPanel from '@/components/pets/PetPanel.vue';
import type { PetDetail } from '@/types';

/**
 * The listing's physical facts.
 *
 * `age` is a varchar column, so it arrives as a string even though it reads
 * numeric; `weight` is a real number and is left unformatted because the unit
 * is not part of the payload.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const items = computed<DetailItem[]>(() => [
    { label: 'Category', value: pet.category?.name ?? null },
    { label: 'Breed', value: pet.breed?.name ?? null },
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
