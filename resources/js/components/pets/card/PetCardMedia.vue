<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleCheck, MapPin, PawPrint } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';
import { show as showPet } from '@/routes/pets';

/**
 * The listing photo, the card's first link to the listing page, and the chips
 * that sit over it.
 *
 * Restyled after legacy's `components/web/PetCard.vue` figure on the user's
 * instruction (2026-09-06): a fixed `h-64` frame, the photo zooming on hover
 * under a dark wash, a "My Listing" pill for the owner at the start corner,
 * and — through the default slot — the listing-type pill at the end corner and
 * the icon bar that slides up from the bottom. What the slot renders is the
 * parent's business; what this file owns is that every overlay is a
 * **sibling** of the link, for the reason below.
 *
 * The chips are SIBLINGS of the `<Link>`, not children. The wrapper `<figure>`
 * is independently required and is not here for positioning: making a chip a
 * sibling leaves several elements at this level, and Vue's single-root-element
 * rule needs a parent for them whatever the chip's positioning turns out to be.
 * It is the `relative` CLASS on that wrapper that exists only for positioning —
 * it establishes the containing block the chips' `absolute start-4 top-4`
 * resolve against. While the distance chip was inside the labelled link, its
 * text was consumed by the link's name computation and thrown away: the AX
 * name sources for that anchor read `aria-label="Photo of Luna"` (used) then
 * `contents: "3.4 km"` (superseded), so whether "3.4 km" reached the user at
 * all was left to the screen reader. On a "pets near you" feed the distance is
 * the sort key and nothing else on the card carries it. As siblings, the
 * anchor's `contents` source is empty and the "3.4 km" StaticText hangs off
 * generic containers instead of off the link, so it is announced in document
 * order. Do not interpolate the distance into the `aria-label` instead — that
 * would tie the accessible name to a formatted number and change it every time
 * the formatting does.
 *
 * `pointer-events-none` on every decorative overlay keeps the whole photo
 * clickable: the hover wash covers the photo entirely and would otherwise
 * swallow every click on it, and a chip would swallow its corner. Hit-tested
 * on the previous layout with `elementFromPoint` at the distance chip's
 * centre: what came back is the `<img>`, whose `closest('a')` is
 * `Photo of Luna` — not the chip. The hover bar in the slot is the one overlay
 * that keeps pointer events, because its icons carry tooltips.
 *
 * Naming: the link's content is an `<img>`, or with no photo an icon, and
 * neither may name it. An `alt` here would either leave the link unnamed
 * (empty `alt`) or duplicate the title link's name (`alt="Luna"`), and a
 * screen-reader user would hear "Luna, link" twice in a row. So the image is
 * `alt=""`, the fallback icon is `aria-hidden`, and the name comes from
 * `aria-label` alone.
 *
 * The owner pill and the distance chip share the start corner; when both
 * render the distance drops one row (`top-14`) rather than overlapping. Legacy
 * had no distance chip at all — it is the nearby feed's, kept from the
 * previous card.
 *
 * The `group-hover:` utilities key off the `group` class on the card's root
 * `<article>`, so the zoom and the wash answer a hover anywhere on the card,
 * exactly as legacy's did.
 */
const { petId, name, image, distance, isOwner } = defineProps<{
    petId: number;
    name: string;
    image: string | null;
    /** Already formatted; null when the feed query ran without a distance. */
    distance: string | null;
    isOwner: boolean;
}>();

const { t } = useTranslations();
</script>

<template>
    <figure class="bg-muted relative h-64 overflow-hidden">
        <Link
            :href="showPet(petId)"
            :aria-label="`Photo of ${name}`"
            class="focus-visible:outline-ring flex size-full items-center justify-center focus-visible:outline-2 focus-visible:-outline-offset-2"
        >
            <img
                v-if="image"
                :src="image"
                alt=""
                class="size-full object-cover transition-transform duration-700 group-hover:scale-110"
                loading="lazy"
            />
            <PawPrint
                v-else
                class="text-muted-foreground size-10"
                aria-hidden="true"
            />
        </Link>

        <div
            class="pointer-events-none absolute inset-0 bg-linear-to-t from-black/60 via-black/20 to-transparent opacity-0 transition-opacity duration-500 ease-in-out group-hover:opacity-100"
            aria-hidden="true"
        ></div>

        <div
            v-if="isOwner"
            class="pointer-events-none absolute start-4 top-4 flex items-center gap-1 rounded-full bg-linear-to-r from-emerald-400 to-teal-500 px-3 py-1 text-xs font-bold tracking-wide text-white shadow-lg shadow-emerald-500/30"
        >
            <CircleCheck class="size-3 shrink-0" aria-hidden="true" />
            {{ t('pets.my_listing') }}
        </div>

        <div
            v-if="distance"
            class="pointer-events-none absolute start-4 flex items-center gap-1 rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold text-gray-800 shadow-md backdrop-blur-sm dark:bg-gray-900/80 dark:text-gray-100"
            :class="isOwner ? 'top-14' : 'top-4'"
        >
            <MapPin class="size-3" aria-hidden="true" />
            {{ distance }}
        </div>

        <slot />
    </figure>
</template>
