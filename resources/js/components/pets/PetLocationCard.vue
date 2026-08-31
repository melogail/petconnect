<script setup lang="ts">
import { MapPin } from '@lucide/vue';
import { computed } from 'vue';
import DetailList from '@/components/DetailList.vue';
import type { DetailItem } from '@/components/DetailList.vue';
import LocationMap from '@/components/pets/LocationMap.vue';
import PetPanel from '@/components/pets/PetPanel.vue';
import { Separator } from '@/components/ui/separator';
import { toCoordinateNumber } from '@/lib/coordinates';
import type { PetDetail } from '@/types';

/**
 * Where the listing is.
 *
 * City, state, country and postal code are public. The street address, the
 * building detail and the coordinates are **absent — not null — for a viewer
 * who cannot update the listing**, so the owner block is gated on `is_owner`
 * and every leaf inside it is still read with `??`.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const publicItems = computed<DetailItem[]>(() => [
    { label: 'City', value: pet.location.city },
    { label: 'State', value: pet.location.state },
    { label: 'Country', value: pet.location.country },
    { label: 'Postal code', value: pet.location.postalCode },
]);

const ownerItems = computed<DetailItem[]>(() => [
    { label: 'Address', value: pet.location.address ?? null },
    { label: 'Building detail', value: pet.location.detailedAddress ?? null },
]);

const lat = computed(() =>
    toCoordinateNumber(pet.location.coordinates?.lat ?? null),
);
const lng = computed(() =>
    toCoordinateNumber(pet.location.coordinates?.lng ?? null),
);
</script>

<template>
    <PetPanel title="Location" :icon="MapPin">
        <div class="space-y-4">
            <DetailList :items="publicItems" />

            <template v-if="pet.is_owner">
                <Separator />
                <div class="space-y-4">
                    <p class="text-muted-foreground text-xs">
                        Only you can see the exact address and pin.
                    </p>
                    <DetailList :items="ownerItems" />
                    <LocationMap :lat="lat" :lng="lng" />
                </div>
            </template>
        </div>
    </PetPanel>
</template>
