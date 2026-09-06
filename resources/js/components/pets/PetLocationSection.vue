<script setup lang="ts">
import { ExternalLink, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import PetSectionHeading from '@/components/pets/PetSectionHeading.vue';
import { useTranslations } from '@/composables/useTranslations';
import { toCoordinateNumber } from '@/lib/coordinates';
import type { PetDetail } from '@/types';

/**
 * Where the listing is — legacy's `PetLocation`, inside the facts card rather
 * than in a sidebar card of its own.
 *
 * City, state, country and postal code are public. The street address, the
 * building detail and the coordinates are **absent — not null — for a viewer
 * who cannot update the listing**, so every one of them is read with `??` and
 * the owner-only rows simply do not render for anybody else. There is no
 * `is_owner` branch here: the payload has already decided, and a second
 * client-side gate would be a place for the two to disagree.
 *
 * ## A link, not an embedded map
 *
 * Legacy offers one thing here — an external "View on Google Maps" link built
 * from the pin — and no embed, so that is what this renders. The previous
 * version of this file mounted `LocationMap` (the Google Maps JS embed) for the
 * owner; that component keeps its consumer in the pet form's `LocationStep`,
 * which is where an owner sets the pin and is the place they see it drawn. No
 * Leaflet, no new dependency, and nothing here loads the Maps SDK on a public
 * page.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const { t } = useTranslations();

const lat = computed(() =>
    toCoordinateNumber(pet.location.coordinates?.lat ?? null),
);
const lng = computed(() =>
    toCoordinateNumber(pet.location.coordinates?.lng ?? null),
);

/** City, state and postal code on one line, then the country, as legacy has it. */
const locality = computed(() =>
    [
        [pet.location.city, pet.location.state].filter(Boolean).join(', '),
        pet.location.postalCode,
    ]
        .filter(Boolean)
        .join(' '),
);

const mapsUrl = computed(() =>
    lat.value === null || lng.value === null
        ? null
        : `https://www.google.com/maps?q=${lat.value},${lng.value}`,
);
</script>

<template>
    <section class="mb-6">
        <PetSectionHeading :title="t('pets.location')" :icon="MapPin" />

        <div
            class="border-border/50 bg-muted/10 space-y-1 rounded-xl border p-4"
        >
            <p
                v-if="pet.location.address"
                class="text-foreground font-medium break-words"
            >
                {{ pet.location.address }}
            </p>
            <p
                v-if="pet.location.detailedAddress"
                class="text-muted-foreground text-sm break-words"
            >
                {{ pet.location.detailedAddress }}
            </p>
            <p v-if="locality" class="text-muted-foreground text-sm">
                {{ locality }}
            </p>
            <p
                v-if="pet.location.country"
                class="text-muted-foreground text-sm"
            >
                {{ pet.location.country }}
            </p>

            <a
                v-if="mapsUrl"
                :href="mapsUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="text-primary mt-2 inline-flex items-center gap-1.5 text-sm font-medium hover:underline"
            >
                <ExternalLink class="size-4" aria-hidden="true" />
                {{ t('pets.view_on_google_maps') }}
            </a>
        </div>
    </section>
</template>
