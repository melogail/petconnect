<script setup lang="ts">
import { DollarSign, Eye, Tag } from '@lucide/vue';
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import type { PetDetail, PetListingType } from '@/types';

/**
 * The listing-type strip — legacy's `PetListingInfo`.
 *
 * A gradient rail across the top of the facts card carrying what the listing
 * *is*: the kind of offer, its price whenever it has one, and the status and
 * view counter repeated from the header. The repetition is legacy's own layout,
 * kept on purpose: this strip is what a reader scanning the facts card sees
 * without scrolling back up to the title block.
 *
 * The three type colours are legacy's — adoption blue, sale green, mating
 * purple — and the map is written over the `PetListingType` union so a fourth
 * type added to the enum fails to type-check instead of falling through to
 * grey.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const { t } = useTranslations();
const { tag } = useLocale();

const typeClass: Record<PetListingType, string> = {
    adoption:
        'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    sale: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    mating: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
};

const typeLabel = computed(() => t(`listing_types.${pet.listing_type}`));

const statusLabel = computed(() =>
    pet.status === 'available' ? t('pets.available') : t('pets.unavailable'),
);

/**
 * `pets.price` is an uncast `decimal` (`DecimalColumn`), so it is a float on
 * SQLite and a string on MySQL. `Number()` is the coercion; passing the raw
 * value happened to work but only because `format()` re-parses a string.
 *
 * The only condition is that the column is non-null, which is the same
 * condition `PetListingCard` formats a feed tile's price under.
 * `PetValidationRules` makes `price` `required_if:listing_type,sale` **and**
 * `nullable`, so a price is *permitted* on an adoption or mating listing, not
 * forbidden — a stud fee on a mating listing is the live case. This component
 * previously also required `listing_type === 'sale'`, so such a listing carried
 * a price on its feed tile and showed none on its own page.
 */
const price = computed(() =>
    pet.price === null
        ? null
        : new Intl.NumberFormat(tag.value, {
              maximumFractionDigits: 2,
          }).format(Number(pet.price)),
);

const views = computed(() =>
    new Intl.NumberFormat(tag.value).format(pet.views),
);
</script>

<template>
    <div
        class="border-border/50 from-muted/30 to-muted/10 mb-6 flex flex-wrap items-center gap-3 rounded-2xl border bg-gradient-to-r px-5 py-4"
    >
        <span
            class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-semibold"
            :class="typeClass[pet.listing_type]"
        >
            <Tag class="size-3.5" aria-hidden="true" />
            {{ typeLabel }}
        </span>

        <span
            v-if="price"
            class="text-foreground inline-flex items-center gap-1 text-2xl font-bold"
        >
            <DollarSign class="size-5 text-green-500" aria-hidden="true" />
            {{ price }}
        </span>

        <div
            class="text-muted-foreground ms-auto flex items-center gap-4 text-sm"
        >
            <span>
                {{ t('pets.status') }}:
                <strong class="text-foreground">{{ statusLabel }}</strong>
            </span>
            <span class="flex items-center gap-1">
                <Eye class="size-4" aria-hidden="true" />
                <span class="sr-only">{{ t('pets.views') }}:</span>
                {{ views }}
            </span>
        </div>
    </div>
</template>
