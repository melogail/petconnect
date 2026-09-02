<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PetCardActions from '@/components/pets/card/PetCardActions.vue';
import PetCardAttributeIcons from '@/components/pets/card/PetCardAttributeIcons.vue';
import PetCardBadges from '@/components/pets/card/PetCardBadges.vue';
import PetCardCommentTeaser from '@/components/pets/card/PetCardCommentTeaser.vue';
import PetCardDescription from '@/components/pets/card/PetCardDescription.vue';
import PetCardHeader from '@/components/pets/card/PetCardHeader.vue';
import PetCardMedia from '@/components/pets/card/PetCardMedia.vue';
import { Card, CardContent } from '@/components/ui/card';
import { useLocale } from '@/composables/useLocale';
import type { PetCard } from '@/types';

/**
 * One listing, as a grid tile.
 *
 * The card used to be a single `<Link>` wrapping everything. It is now three
 * discrete targets to the same page — the photo, the name, and an explicit
 * "View details" — each with its own accessible name, which is what let the
 * like, comment, message and share controls land in `PetCardActions` without
 * nesting a button inside an anchor.
 *
 * `canInteract` is derived here, off `auth.user`, exactly as
 * `pages/pets/Show.vue` derives `isSignedIn`. It cannot be a prop: both
 * consumers of this card — `PetFeed.vue` (via `Home.vue`) and
 * `profile/ProfileListings.vue` — pass nothing but `pet`, and neither should
 * have to learn about authentication to render a tile. `auth.user` is null for
 * a guest whatever `types/auth.ts` says, hence the `Boolean`.
 *
 * `overflow-hidden` on the root is not only for the photo's rounded corner. The
 * card is a grid item in both consumers (`PetFeed.vue`,
 * `profile/ProfileListings.vue`), and a grid item's automatic minimum size is
 * its min-content width — except that a box whose `overflow` is not `visible`
 * gets an automatic minimum size of zero instead. `PetCardHeader`'s
 * `kind · place` line is `truncate`, i.e. `white-space: nowrap`, so it
 * contributes its whole unwrapped width.
 *
 * The absolute widths are a function of the string being measured, so the
 * invariant is the part worth recording: without `overflow-hidden` the card is
 * sized to that nowrap line's min-content width **plus 34px** — `CardContent`'s
 * `p-4` contributing 16px of padding per side and `Card`'s `border` 1px per
 * side (measured on the rendered card as `padding-left`/`padding-right` 16px,
 * `border-left-width`/`border-right-width` 1px). With `overflow-hidden` the
 * card is the grid track's width instead and the ellipsis applies.
 *
 * Worked example, naming its input so it can be re-run: at a 320px viewport,
 * with kind "Golden Retriever" and place "Sheikh Zayed City, Sixth of October
 * Governorate, Arab Republic of Egypt", that line's min-content measures
 * 592.625px. With `overflow-hidden` the card measures 320px and the document
 * does not scroll sideways (`documentElement.scrollWidth` 320px); remove the
 * class and the card measures 626.625px — 592.625 + 34 — and the document does
 * (`scrollWidth` 628px). The same fixture in `rtl`, whose Arabic strings give a
 * 456.359px line, comes out at 490.359px: a different pair, the same +34.
 * Control-measured both ways, in both directions, before this was written down.
 *
 * These figures came off the harness described in `card/PetCardCommentPreview`
 * and only mean something on it: the `resources/css/app.css` manifest key (not
 * the `resources/js/app.ts` one), an explicit `document.fonts.load` per face
 * and weight, and the stylesheet-live and `innerWidth` assertions. Re-run them
 * there before trusting a re-measurement here.
 */
const { pet } = defineProps<{ pet: PetCard }>();

const { tag } = useLocale();

const page = usePage();

/** A signed-in viewer. Every write the action row offers needs one. */
const canInteract = computed(() => Boolean(page.props.auth.user));

const place = computed(() =>
    [pet.city, pet.state, pet.country].filter(Boolean).join(', '),
);

/** Only present when the feed query ran with a distance calculation. */
const distance = computed(() =>
    pet.distance === undefined ? null : `${pet.distance} km`,
);

/**
 * `pets.price` is an uncast `decimal` (`DecimalColumn`), so it is a float on
 * SQLite and a string on MySQL. `Number()` is the coercion; passing the raw
 * value happened to work but only because `format()` re-parses a string.
 */
const price = computed(() =>
    pet.price === null
        ? null
        : new Intl.NumberFormat(tag.value, {
              maximumFractionDigits: 2,
          }).format(Number(pet.price)),
);

/** Breed names the listing best; category is the fallback, then a generic. */
const kind = computed(() => pet.breed?.name ?? pet.category?.name ?? 'Pet');
</script>

<template>
    <Card class="overflow-hidden py-0 transition-shadow hover:shadow-md">
        <PetCardMedia
            :pet-id="pet.id"
            :name="pet.name"
            :image="pet.image"
            :distance="distance"
        />

        <CardContent class="space-y-3 p-4">
            <PetCardBadges
                :status="pet.status"
                :listing-type="pet.listing_type"
            />

            <PetCardHeader
                :pet-id="pet.id"
                :name="pet.name"
                :kind="kind"
                :place="place"
                :price="price"
            />

            <PetCardAttributeIcons
                :age="pet.age"
                :gender="pet.gender"
                :vaccinated="pet.vaccinated"
                :spayed-neutered="pet.spayed_neutered"
            />

            <PetCardDescription :description="pet.description" />

            <PetCardActions :pet="pet" :can-interact="canInteract" />

            <PetCardCommentTeaser
                :pet-id="pet.id"
                :name="pet.name"
                :comments="pet.comments"
                :comments-count="pet.comments_count"
            />
        </CardContent>
    </Card>
</template>
