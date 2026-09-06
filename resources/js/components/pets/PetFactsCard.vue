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
 * ## The card is a visual grouping and not an outline level
 *
 * It renders **no heading of its own**, deliberately: legacy draws none, and
 * the only name the block could take is the listing's, which is already the
 * `h1` in `PetDetailHeader`. So the outline stays flat — `PetQuickInfo`,
 * `PetAboutSection`, `PetLocationSection`, `PetHealthSection` and `PetExtras`
 * each open a top-level `h2`, siblings of the comment thread's and the owner
 * panel's, rather than children of the card.
 *
 * Read that as a constraint on the blocks, not as trivia: `PetSectionHeading`'s
 * `level` default is `h3`, and its docblock justified that by "the card's own
 * `h2`" — a heading this template has never contained. Under that assumption
 * Location, Health and Extras nested under "Meet Luna!" in the outline. If a
 * heading is ever added here, the four `level="h2"` call sites are what has to
 * move with it.
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
