<script setup lang="ts">
import { Cake, Mars, Scissors, Syringe, Venus } from '@lucide/vue';
import { computed, type Component } from 'vue';
import type { PetGender } from '@/types';

/** One icon-and-label chip in the at-a-glance row. */
type Attribute = {
    key: string;
    icon: Component;
    label: string;
};

/**
 * The four facts a browser scans for before opening a listing.
 *
 * `vaccinated` and `spayed_neutered` are shown only when true: a chip that
 * says "Vaccinated" is information, and its absence is the same information
 * without spending a line of a 320px-wide card on it.
 */
const { age, gender, vaccinated, spayedNeutered } = defineProps<{
    /** A varchar column, so a string even though it reads numeric. */
    age: string;
    gender: PetGender;
    vaccinated: boolean;
    spayedNeutered: boolean;
}>();

/**
 * Spaying is the female procedure, neutering the male one.
 *
 * The legacy card had this exactly backwards — it mapped `male` to "Spayed"
 * and `female` to "Neutered". The payload carries one boolean plus `gender`,
 * so the label is derived here. Do not port the old mapping back.
 */
const sterilisationLabel = computed(() =>
    gender === 'female' ? 'Spayed' : 'Neutered',
);

const attributes = computed<Attribute[]>(() => {
    const candidates: (Attribute | null)[] = [
        vaccinated
            ? { key: 'vaccinated', icon: Syringe, label: 'Vaccinated' }
            : null,
        spayedNeutered
            ? {
                  key: 'sterilised',
                  icon: Scissors,
                  label: sterilisationLabel.value,
              }
            : null,
        age === '' ? null : { key: 'age', icon: Cake, label: `${age} yrs` },
        {
            key: 'gender',
            icon: gender === 'female' ? Venus : Mars,
            label: gender === 'female' ? 'Female' : 'Male',
        },
    ];

    return candidates.filter(
        (attribute): attribute is Attribute => attribute !== null,
    );
});
</script>

<template>
    <ul
        class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
    >
        <li
            v-for="attribute in attributes"
            :key="attribute.key"
            class="flex items-center gap-1 whitespace-nowrap"
        >
            <component
                :is="attribute.icon"
                class="size-3.5"
                aria-hidden="true"
            />
            {{ attribute.label }}
        </li>
    </ul>
</template>
