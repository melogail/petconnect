<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Mars, PawPrint, Venus } from '@lucide/vue';
import { computed } from 'vue';
import { ageLabel } from '@/components/pets/card/labels';
import { useTranslations } from '@/composables/useTranslations';
import { show as showPet } from '@/routes/pets';
import type { PetGender } from '@/types';

/**
 * Who the listing is: the name, its price, a gender pill, and under them the
 * breed and age line — legacy's "Pet Name and Gender" and "Breed and Age"
 * blocks (`components/web/PetCard.vue`), restyled on the user's instruction
 * (2026-09-06). Legacy showed no price; this card did, and it stays between
 * the name and the pill rather than being dropped for the sake of the port.
 * Legacy showed no location line either — the place moved into the hover bar's
 * tooltip (`PetCardAttributeIcons`), which is where legacy kept it.
 *
 * The name is the card's second link to the listing page and takes its
 * accessible name from its own text, so it announces as "Luna, link" — the
 * media link above it is labelled "Photo of Luna" and the action below it
 * names the same page with the "View details" text, which is what keeps the
 * three apart.
 *
 * `truncate` is on the `<a>`, not on the `<h3>`. `truncate` sets
 * `overflow: hidden`, and on the `<h3>` that clipped the link's focus ring —
 * the ring is a 3px-spread `box-shadow` and the `<h3>` left exactly 0px of
 * horizontal slack on either side of the `<a>`. The computed style will not
 * show you this: it reads the same 3px `oklab(... / 0.5)` shadow either way.
 * Only the painted pixels do. That identity is narrow, and here is the scope
 * it holds within, so it is not carried past it: what differs between the two
 * arrangements is the **ancestor's** clip box, not any property of the `<a>`
 * itself, so this element has nothing for computed style to differ *on*. Where
 * the differing property does belong to the element, computed style sees it
 * perfectly well — `pr-6` against `pe-6` under `dir="rtl"` yields a different
 * `padding-right` / `padding-left`, and reaching for painted pixels there would
 * be reading this observation outside the clause that bounds it.
 * Screenshotted at a 320px viewport with the link focused and the ring
 * animation settled, sampling 2px outside each vertical edge of the link box:
 * rgb(255,255,255) — bare card — with `truncate` on the `<h3>`,
 * rgb(132,132,132) — ring — with it on the `<a>`, in both `ltr` and `rtl`. An
 * element's own `overflow` never clips its own `box-shadow`, so moving
 * `truncate` down one level keeps the ellipsis and frees the ring. The `<h3>`
 * keeps `min-w-0` because it is a flex item and still has to be allowed to
 * shrink below its content. (Measured on the previous, `font-medium` markup;
 * the arrangement is unchanged, the type scale is not.)
 *
 * `price` arrives already formatted: the raw column is an uncast `decimal`
 * and the `Intl` coercion belongs with the locale, not here.
 */
const { gender, age } = defineProps<{
    petId: number;
    name: string;
    /** Breed, falling back to category, falling back to "Pet". */
    kind: string;
    price: string | null;
    gender: PetGender;
    /** A varchar column, so a string even though it reads numeric. */
    age: string;
}>();

const { t } = useTranslations();

const genderLabel = computed(() =>
    t(gender === 'female' ? 'pets.female' : 'pets.male'),
);

const ageText = computed(() => (age === '' ? null : ageLabel(t, age)));
</script>

<template>
    <div class="space-y-2.5">
        <div class="flex items-center justify-between gap-2">
            <h3
                class="text-foreground group-hover:text-primary-600 dark:group-hover:text-primary-400 min-w-0 flex-1 text-xl font-extrabold transition-colors duration-300"
            >
                <Link
                    :href="showPet(petId)"
                    class="focus-visible:ring-ring/50 block truncate rounded-sm hover:underline focus-visible:ring-[3px] focus-visible:outline-none"
                >
                    {{ name }}
                </Link>
            </h3>

            <span v-if="price" class="shrink-0 text-sm font-bold">
                {{ price }}
            </span>

            <span
                class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="
                    gender === 'female'
                        ? 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300'
                        : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
                "
            >
                <component
                    :is="gender === 'female' ? Venus : Mars"
                    class="size-3"
                    aria-hidden="true"
                />
                {{ genderLabel }}
            </span>
        </div>

        <div class="text-muted-foreground flex items-center gap-3 text-sm">
            <span class="flex min-w-0 items-center gap-1.5">
                <PawPrint class="size-4 shrink-0" aria-hidden="true" />
                <span class="text-foreground/80 truncate font-medium">
                    {{ kind }}
                </span>
            </span>

            <template v-if="ageText">
                <span
                    class="bg-border size-1 shrink-0 rounded-full"
                    aria-hidden="true"
                ></span>
                <span class="flex shrink-0 items-center gap-1.5">
                    <Clock class="size-4" aria-hidden="true" />
                    <span class="text-foreground/80 font-medium">
                        {{ ageText }}
                    </span>
                </span>
            </template>
        </div>
    </div>
</template>
