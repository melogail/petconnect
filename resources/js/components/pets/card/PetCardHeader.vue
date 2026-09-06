<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show as showPet } from '@/routes/pets';

/**
 * Who the listing is, and what it costs.
 *
 * The name is the card's second link to the listing page and takes its
 * accessible name from its own text, so it announces as "Luna, link" — the
 * media link above it is labelled "Photo of Luna" and the action below it
 * "View details for Luna", which is what keeps the three apart.
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
 * Screenshotted at a 320px viewport with the link
 * focused and the ring animation settled, sampling 2px outside each vertical
 * edge of the link box: rgb(255,255,255) — bare card — with `truncate` on the
 * `<h3>`, rgb(132,132,132) — ring — with it on the `<a>`, in both `ltr` and
 * `rtl`. An element's own `overflow` never clips its own `box-shadow`, so
 * moving `truncate` down one level keeps the ellipsis and frees the ring. The
 * `<h3>` keeps `min-w-0` because it is a flex item and still has to be allowed
 * to shrink below its content.
 *
 * `price` arrives already formatted: the raw column is an uncast `decimal`
 * and the `Intl` coercion belongs with the locale, not here.
 */
defineProps<{
    petId: number;
    name: string;
    /** Breed, falling back to category, falling back to "Pet". */
    kind: string;
    /** `city, state, country`, minus the empty parts. */
    place: string;
    price: string | null;
}>();
</script>

<template>
    <div class="space-y-1">
        <div class="flex items-start justify-between gap-2">
            <h3 class="min-w-0 font-medium">
                <Link
                    :href="showPet(petId)"
                    class="focus-visible:ring-ring/50 block truncate rounded-sm hover:underline focus-visible:ring-[3px] focus-visible:outline-none"
                >
                    {{ name }}
                </Link>
            </h3>
            <span v-if="price" class="shrink-0 text-sm font-semibold">
                {{ price }}
            </span>
        </div>

        <p class="text-muted-foreground truncate text-sm">
            {{ kind }}
            <template v-if="place"> · {{ place }}</template>
        </p>
    </div>
</template>
