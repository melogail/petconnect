<script setup lang="ts">
import { computed } from 'vue';
import { LISTING_TYPE_PILL_CLASSES } from '@/components/pets/listingTypeStyles';
import { useTranslations } from '@/composables/useTranslations';
import type { PetListingType, PetStatus } from '@/types';

/**
 * The pills at the photo's end corner: what kind of listing this is, and —
 * only when it matters — that it is no longer available.
 *
 * Legacy (`components/web/PetCard.vue`, the "Status Badge" block) rendered one
 * gradient pill over the photo, amber→rose for a sale and teal→emerald for an
 * adoption, and nothing for anything else. The "Aubergine & Lavender" restyle
 * first flattened every type to one solid violet; the user then asked for the
 * type coding and tonal gradients back, so the pill now takes its classes
 * from `pets/listingTypeStyles.ts` — brand violet for adoption, emerald for a
 * sale, amber for mating, each a two-step gradient within one hue, and the
 * same map the detail page's summary uses. What is kept from legacy is that
 * every type gets a pill at all: a mating listing is not the only kind that
 * goes unlabelled. The two custom keys (`for_sale`, `for_adoption`) are still
 * looked up per type; `LISTING_KEYS` holds only the translation key, and the
 * fallback builds one from the enum value.
 *
 * `status` used to lead the pair as a text badge under the photo. It is
 * `available | unavailable`, and "Available" is the case every listing on the
 * feed is in — so the pill is rendered only for `unavailable`, which is the one
 * value that rules a listing out. It is the guide's over-photo chip —
 * `bg-card/90 text-foreground backdrop-blur`, no coloured shadow — so it reads
 * as information rather than as a second accent. The parent positions this
 * block; it renders `pointer-events-none` so the photo under it stays
 * clickable.
 */
const { status, listingType } = defineProps<{
    status: PetStatus;
    listingType: PetListingType;
}>();

const { t } = useTranslations();

const LISTING_KEYS: Partial<Record<PetListingType, string>> = {
    sale: 'listing_types.for_sale',
    adoption: 'listing_types.for_adoption',
};

const pillKey = computed(
    () => LISTING_KEYS[listingType] ?? `listing_types.${listingType}`,
);
</script>

<template>
    <div
        class="pointer-events-none absolute end-4 top-4 flex flex-col items-end gap-2"
    >
        <span
            class="rounded-full px-3 py-1 text-[11px] font-semibold"
            :class="LISTING_TYPE_PILL_CLASSES[listingType]"
        >
            {{ t(pillKey) }}
        </span>

        <span
            v-if="status !== 'available'"
            class="bg-card/90 text-foreground rounded-full px-3 py-1 text-[11px] font-semibold backdrop-blur"
        >
            {{ t('pets.unavailable') }}
        </span>
    </div>
</template>
