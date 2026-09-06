<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import type { PetListingType, PetStatus } from '@/types';

/**
 * The pills at the photo's end corner: what kind of listing this is, and —
 * only when it matters — that it is no longer available.
 *
 * Legacy (`components/web/PetCard.vue`, the "Status Badge" block) rendered one
 * gradient pill over the photo, amber→rose for a sale and teal→emerald for an
 * adoption, and nothing for anything else. This keeps those two exactly and
 * gives the third listing type the brand gradient rather than no pill, so a
 * mating listing is not the only kind that goes unlabelled.
 *
 * `status` used to lead the pair as a text badge under the photo. It is
 * `available | unavailable`, and "Available" is the case every listing on the
 * feed is in — so the pill is rendered only for `unavailable`, which is the one
 * value that rules a listing out. The parent positions this block; it renders
 * `pointer-events-none` so the photo under it stays clickable.
 */
const { status, listingType } = defineProps<{
    status: PetStatus;
    listingType: PetListingType;
}>();

const { t } = useTranslations();

type Pill = { key: string; classes: string };

const LISTING_PILLS: Partial<Record<PetListingType, Pill>> = {
    sale: {
        key: 'listing_types.for_sale',
        classes: 'from-amber-400 to-rose-500 shadow-amber-500/30',
    },
    adoption: {
        key: 'listing_types.for_adoption',
        classes: 'from-teal-400 to-emerald-500 shadow-teal-500/30',
    },
};

const pill = computed<Pill>(
    () =>
        LISTING_PILLS[listingType] ?? {
            key: `listing_types.${listingType}`,
            classes: 'from-violet-500 to-fuchsia-500 shadow-violet-500/30',
        },
);
</script>

<template>
    <div
        class="pointer-events-none absolute end-4 top-4 flex flex-col items-end gap-2"
    >
        <span
            class="rounded-full bg-linear-to-r px-3 py-1 text-xs font-bold tracking-wide text-white shadow-lg"
            :class="pill.classes"
        >
            {{ t(pill.key) }}
        </span>

        <span
            v-if="status !== 'available'"
            class="rounded-full bg-gray-900/80 px-3 py-1 text-xs font-bold tracking-wide text-white shadow-lg backdrop-blur-sm"
        >
            {{ t('pets.unavailable') }}
        </span>
    </div>
</template>
