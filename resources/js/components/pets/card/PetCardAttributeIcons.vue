<script setup lang="ts">
import { Clock, MapPin, Scissors, Syringe } from '@lucide/vue';
import { computed, type Component } from 'vue';
import { ageLabel } from '@/components/pets/card/labels';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';
import type { PetGender } from '@/types';

/** One round icon in the bar, with the sentence its tooltip shows. */
type Attribute = {
    key: string;
    icon: Component;
    label: string;
    color: string;
};

/**
 * The facts a browser scans for before opening a listing, as legacy showed
 * them: a bar of round icons that slides up over the bottom of the photo on
 * hover, each with a tooltip (`components/web/PetCard.vue`, the "Status
 * Icons with Tooltips" block).
 *
 * Restyled from the text-chip row on the user's instruction (2026-09-06). The
 * chips were readable without a pointer; the bar is not, so two things keep it
 * reachable: every icon is a `TooltipTrigger` — a real button with the
 * sentence as its `aria-label`, so a screen reader announces it and a keyboard
 * user can tab to it — and the bar also reveals on `group-focus-within`, so
 * tabbing onto an icon does not land on an invisible control.
 *
 * `vaccinated` and `spayed_neutered` are shown only when true: an icon that
 * says "Vaccinated" is information, and its absence is the same information
 * without spending a slot on it. Location is shown only when there is one.
 *
 * Spaying is the female procedure, neutering the male one. The legacy card had
 * this exactly backwards — it mapped `male` to "Spayed" and `female` to
 * "Neutered". The payload carries one boolean plus `gender`, so the label is
 * derived here. Do not port the old mapping back.
 *
 * The `group-hover:` and `group-focus-within:` utilities key off the `group`
 * class on the card's root `<article>`.
 */
const { age, gender, vaccinated, spayedNeutered, place } = defineProps<{
    /** A varchar column, so a string even though it reads numeric. */
    age: string;
    gender: PetGender;
    vaccinated: boolean;
    spayedNeutered: boolean;
    /** `city, state, country`, minus the empty parts; empty when unknown. */
    place: string;
}>();

const { t } = useTranslations();

const attributes = computed<Attribute[]>(() => {
    const candidates: (Attribute | null)[] = [
        vaccinated
            ? {
                  key: 'vaccinated',
                  icon: Syringe,
                  label: t('pets.vaccinated'),
                  color: 'text-green-600',
              }
            : null,
        spayedNeutered
            ? {
                  key: 'sterilised',
                  icon: Scissors,
                  label: t(
                      gender === 'female' ? 'pets.spayed' : 'pets.neutered',
                  ),
                  color: 'text-blue-600',
              }
            : null,
        age === ''
            ? null
            : {
                  key: 'age',
                  icon: Clock,
                  label: ageLabel(t, age),
                  color: 'text-amber-600',
              },
        place === ''
            ? null
            : {
                  key: 'location',
                  icon: MapPin,
                  label: t('pets.located_in', { location: place }),
                  color: 'text-purple-600',
              },
    ];

    return candidates.filter(
        (attribute): attribute is Attribute => attribute !== null,
    );
});
</script>

<template>
    <div
        class="absolute inset-x-0 bottom-0 translate-y-2 bg-linear-to-t from-black/80 via-black/50 to-transparent p-4 pt-10 opacity-0 transition-all duration-500 ease-out group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:translate-y-0 group-hover:opacity-100"
    >
        <TooltipProvider>
            <ul class="flex items-center justify-center gap-3">
                <li v-for="attribute in attributes" :key="attribute.key">
                    <Tooltip>
                        <TooltipTrigger
                            :aria-label="attribute.label"
                            class="flex size-8 items-center justify-center rounded-full bg-white/90 shadow-sm backdrop-blur-sm transition-transform duration-200 hover:scale-110 hover:bg-white"
                            :class="attribute.color"
                        >
                            <component
                                :is="attribute.icon"
                                class="size-4"
                                aria-hidden="true"
                            />
                        </TooltipTrigger>
                        <TooltipContent>{{ attribute.label }}</TooltipContent>
                    </Tooltip>
                </li>
            </ul>
        </TooltipProvider>
    </div>
</template>
