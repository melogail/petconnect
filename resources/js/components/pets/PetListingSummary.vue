<script setup lang="ts">
import { DollarSign, Eye, Tag } from '@lucide/vue';
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { LISTING_TYPE_PILL_CLASSES } from '@/components/pets/listingTypeStyles';
import type { PetDetail, PetListingType } from '@/types';

/**
 * The listing-type strip — legacy's `PetListingInfo`.
 *
 * A rail across the top of the facts card carrying what the listing *is*: the
 * kind of offer, its price whenever it has one, and the status and view
 * counter repeated from the header. The repetition is legacy's own layout,
 * kept on purpose: this strip is what a reader scanning the facts card sees
 * without scrolling back up to the title block.
 *
 * Legacy drew the rail as a grey gradient wash with a three-way colour code
 * on the type pill — adoption blue, sale green, mating purple. The rail is
 * now the lavender `bg-gradient-wash` (primary-100 into the card colour) at
 * the card radius with a `--border` hairline, and the type pill takes its
 * classes from `pets/listingTypeStyles.ts` — the same brand / emerald / amber
 * tonal gradients the feed card badge wears, so a type never has two colours.
 * The price is `--primary` at weight 600 as it is on the card. The shared map
 * is written over the `PetListingType` union, so a fourth type added to the
 * enum fails to type-check instead of falling through silently.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const { t } = useTranslations();
const { tag } = useLocale();

const typeClass: Record<PetListingType, string> = LISTING_TYPE_PILL_CLASSES;

/**
 * Neither of these inherits `typeClass`'s exhaustiveness, and they sit close
 * enough to it to be read as if they did.
 *
 * `typeLabel` builds its key from the value, so a new `PetListingType` type-
 * checks fine and renders the raw string `listing_types.<whatever>` — `t()`
 * returns a missing key unchanged, by design. A new listing type does trip the
 * map above, which is what would bring a reader here.
 *
 * `statusLabel`'s ternary sends any status that is not `available` to
 * "Unavailable", and nothing in this file is exhaustive over `PetStatus` — the
 * only tripwire for that union is `statusClass` in `PetDetailHeader`, a
 * different file that happens to render the same strip's status. Say it rather
 * than leave it implied: a third status is caught there or not at all.
 */
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
        class="border-border bg-secondary bg-gradient-wash mb-6 flex flex-wrap items-center gap-3 rounded-[20px] border px-5 py-4"
    >
        <span
            class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[11px] font-semibold"
            :class="typeClass[pet.listing_type]"
        >
            <Tag class="size-3.5" aria-hidden="true" />
            {{ typeLabel }}
        </span>

        <span
            v-if="price"
            class="text-primary inline-flex items-center gap-1 text-2xl font-semibold"
        >
            <DollarSign class="size-5" aria-hidden="true" />
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
