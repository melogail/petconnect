<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, PawPrint } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { show as showPet } from '@/routes/pets';

/**
 * The listing photo, the card's first link to the listing page, and the
 * distance badge that sits over it.
 *
 * The badge is a SIBLING of the `<Link>`, not a child. The wrapper `<div>` is
 * independently required and is not here for positioning: making the badge a
 * sibling leaves two elements at this level, and Vue's single-root-element rule
 * needs a parent for them whatever the badge's positioning turns out to be. It
 * is the `relative` CLASS on that wrapper that exists only for positioning — it
 * establishes the containing block the badge's `absolute start-2 top-2`
 * resolves against. While the badge was inside the labelled link, its
 * text was consumed by the link's name computation and thrown away: the AX
 * name sources for that anchor read
 * `aria-label="Photo of Luna"` (used) then `contents: "3.4 km"` (superseded),
 * so whether "3.4 km" reached the user at all was left to the screen reader.
 * On a "pets near you" feed the distance is the sort key and nothing else on
 * the card carries it. As siblings, the anchor's `contents` source is empty and
 * the "3.4 km" StaticText hangs off generic containers instead of off the link,
 * so it is announced in document order. Do not interpolate the distance into
 * the `aria-label` instead — that would tie the accessible name to a formatted
 * number and change it every time the formatting does.
 *
 * `pointer-events-none` on the badge keeps the whole photo clickable. As a
 * child of the anchor a click on the badge still activated the link, because
 * the badge was inside its `closest('a')`; as an overlaying sibling it would
 * swallow that corner of the photo instead. Hit-tested with
 * `elementFromPoint` at the badge's centre: what comes back is the `<img>`,
 * whose `closest('a')` is `Photo of Luna` — not the badge.
 *
 * Naming: the link's content is an `<img>`, or with no photo an icon, and
 * neither may name it. An `alt` here would either leave the link unnamed
 * (empty `alt`) or duplicate the title link's name (`alt="Luna"`), and a
 * screen-reader user would hear "Luna, link" twice in a row. So the image is
 * `alt=""`, the fallback icon is `aria-hidden`, and the name comes from
 * `aria-label` alone.
 *
 * Established by SSR-rendering `PetListingCard` and reading the emitted markup
 * and the CDP accessibility tree, not by reasoning about them. The wrapper
 * emits two children, `A` and `DIV[data-slot=badge]`; the anchor emits one,
 * `IMG` — or, on the fixture with `image: null` and no distance, one
 * `aria-hidden` `svg` and no badge at all. `closest('a')` from the badge
 * returns null. The card's three anchors come out with
 * `aria-label="Photo of Luna"`, no label and the text "Luna", and
 * `aria-label="View details for Luna"` — three distinct accessible names.
 */
const { petId, name, image, distance } = defineProps<{
    petId: number;
    name: string;
    image: string | null;
    /** Already formatted; null when the feed query ran without a distance. */
    distance: string | null;
}>();
</script>

<template>
    <div class="relative">
        <Link
            :href="showPet(petId)"
            :aria-label="`Photo of ${name}`"
            class="bg-muted focus-visible:outline-ring flex aspect-4/3 items-center justify-center focus-visible:outline-2 focus-visible:-outline-offset-2"
        >
            <img
                v-if="image"
                :src="image"
                alt=""
                class="size-full object-cover"
                loading="lazy"
            />
            <PawPrint
                v-else
                class="text-muted-foreground size-10"
                aria-hidden="true"
            />
        </Link>

        <Badge
            v-if="distance"
            variant="secondary"
            class="pointer-events-none absolute start-2 top-2"
        >
            <MapPin class="size-3" aria-hidden="true" />
            {{ distance }}
        </Badge>
    </div>
</template>
