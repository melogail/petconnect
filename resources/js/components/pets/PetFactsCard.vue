<script setup lang="ts">
import PetAboutSection from '@/components/pets/PetAboutSection.vue';
import PetExtras from '@/components/pets/PetExtras.vue';
import PetHealthSection from '@/components/pets/PetHealthSection.vue';
import PetListingSummary from '@/components/pets/PetListingSummary.vue';
import PetLocationSection from '@/components/pets/PetLocationSection.vue';
import PetQuickInfo from '@/components/pets/PetQuickInfo.vue';
import type { PetDetail } from '@/types';

/**
 * Everything a listing says about itself, in **one** card.
 *
 * This is the shape legacy uses and the biggest structural difference from what
 * this page looked like before: three separate `Card`s (about, attributes,
 * health) in a two-thirds column plus a location card in the sidebar, against
 * legacy's single `rounded-2xl` card whose blocks are separated by a rule and
 * by the small uppercase headings `PetSectionHeading` renders. The location
 * moves **into** this card with them, so the sidebar is left holding only the
 * two things that are about the transaction rather than about the animal: who
 * is offering it, and how to meet safely.
 *
 * Order is legacy's, and it is not arbitrary — it goes from the facts you can
 * check at a glance to the ones you read: quick-info tiles, the listing-type
 * strip, a rule, the description and traits, the location, the health group,
 * then the owner's free-form extras. The rule after the strip is the only
 * divider legacy draws inside the card; the headings do the rest of the
 * separating.
 *
 * `PetAttributesCard` is gone rather than moved. Its six rows are all here:
 * category and breed are badges in `PetDetailHeader`, age and gender are its
 * meta line, colour and weight are `PetQuickInfo` tiles — which is where legacy
 * puts each of them.
 */
defineProps<{ pet: PetDetail }>();
</script>

<template>
    <div
        class="border-border/50 bg-card mb-6 overflow-hidden rounded-2xl border shadow-sm"
    >
        <div class="p-6">
            <PetQuickInfo :pet="pet" />
            <PetListingSummary :pet="pet" />

            <div class="border-border/50 mb-6 border-t" />

            <PetAboutSection :pet="pet" />
            <PetLocationSection :pet="pet" />
            <PetHealthSection :pet="pet" />
            <PetExtras :pet="pet" />
        </div>
    </div>
</template>
