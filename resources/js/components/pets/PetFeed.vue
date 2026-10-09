<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { computed } from 'vue';
import PetListingCard from '@/components/pets/PetListingCard.vue';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated, PetCard } from '@/types';

/**
 * The discovery grid.
 *
 * `pets` ships from `Inertia::scroll()`, so the payload carries
 * `mergeProps: ["pets.data"]` and the cursor metadata `<InfiniteScroll>` reads.
 * The component owns the whole append cycle — no `router.reload` handler here,
 * and no page-number state: a manual reload would replace `pets.data` instead
 * of merging into it and leave the visitor looking at page 2 alone.
 *
 * ## The footer is the `next` slot, not the `loading` one
 *
 * `<InfiniteScroll>` renders its own trigger `<div>` after the items and fills
 * it from `#next` when that slot exists, falling back to `#loading` only when
 * it does not (`node_modules/@inertiajs/vue3/dist/index.js`, the
 * `exposedNext` branch). The legacy component put two states in that one place
 * — a spinner while fetching, an end-of-list line once there is nothing left —
 * and `#loading` cannot express the second, because it is rendered only while
 * `loadingNext` is true. `#next` receives both `loading` and `hasMore`, so the
 * whole footer is one slot. Providing it does not turn off automatic loading:
 * the observers are disabled by `manual`/`manualAfter` or by passing an
 * `end-element`, neither of which is used here.
 *
 * The end-of-list line is suppressed on an empty feed, where `hasMore` is
 * false but "you have reached the end" under "No pets found" says nothing. The
 * legacy page achieved this by not mounting its scroller at all when the list
 * was empty.
 *
 * ## `only-next`, because the legacy scroller was forward-only
 *
 * `<InfiniteScroll>` is bidirectional by default: with neither `start-element`
 * nor `only-next` given it renders a *previous* trigger of its own above the
 * items and reports `shouldFetchPrevious: () => !props.onlyNext`
 * (`node_modules/@inertiajs/vue3/dist/index.js`). On page 1 that costs nothing
 * — there is no previous page to fetch — but `pets` is page-based, so `/?page=3`
 * is a reachable URL, and there scrolling up would prepend page 2 into a feed
 * the legacy page would only ever have grown downwards. `only-next` is the
 * parity-correct setting, not a tuning choice.
 *
 * Measured with it in place: `/` scrolled to the bottom twelve times reaches
 * **57 cards**, shows the end-of-list line and takes the URL to `?page=5`, so
 * forward loading is untouched; `/?page=3` scrolled back to the top stays at
 * **12 cards**, so nothing is prepended. No console warning or error on either.
 *
 * ## `locating`
 *
 * `Home.vue` resolves the visitor's position on mount and then navigates, and
 * that gap is dead air the feed would otherwise not account for — the legacy
 * page fed the same flag into its scroller's loading state. It is an input to
 * the spinner only; this component neither knows nor cares what it means.
 *
 * ## `nearby`
 *
 * Only the empty copy needs it: "no pets found near your location" is a
 * different statement from "no pets found", and getting it wrong tells a
 * visitor their filters are empty when the truth is that nothing is close by.
 *
 * ## Measured
 *
 * Against the running application in headless Chrome, on the feed as served
 * (`?age_min=0&age_max=0.2` for an empty non-nearby feed, `?latitude=0&
 * longitude=0&radius=5` for an empty nearby one), asserting first that the
 * stylesheet is live — `--primary` non-empty and the body in the app's sans
 * face (Instrument Sans when measured; Manrope since the "Aubergine &
 * Lavender" restyle), because a cold navigation against `artisan serve` can
 * lose the CSS request outright and then reads as one column and a black
 * heading:
 *
 * - Columns at 375 / 640 / 1024 / 1280px: **1 / 2 / 3 / 4**, so `xl:grid-cols-4`
 *   is doing something at the width the legacy grid intended it for.
 * - `column-gap` and `row-gap` **24px** at every one of those widths.
 * - Spinner, caught mid-fetch while scrolling: **32 × 32px**,
 *   `rgb(124, 58, 237)` — the `--primary-600` of the time, the legacy
 *   `text-primary` — with `animation-name: spin`, inside `mt-8 flex
 *   justify-center py-4`. The class is still `text-primary`; the token now
 *   resolves to `#5A3FE0` (`rgb(90, 63, 224)`) in light and `#A78BFF` in dark,
 *   per the guide's ramp. Not re-measured.
 * - End of the list, after 57 cards: `You've reached the end of the list.`,
 *   and **absent** on both empty feeds.
 * - Empty: `text-muted-foreground col-span-full py-12 text-center`, reading
 *   `No pets found` and `No pets found near your location` respectively.
 *
 * ## Both greys are `text-muted-foreground`, not the legacy literals
 *
 * A deliberate, reportable divergence: the legacy markup carried
 * `text-gray-500` on the empty state and `text-gray-400` on the end-of-list
 * line, and **matching that hex does not match that appearance.** The legacy
 * app had no tinted dark scheme, so those two classes on *this* `--background`
 * render a contrast the legacy screen never produced.
 *
 * Measured, at the time, from Tailwind v4's own oklch stops
 * (`node_modules/tailwindcss/theme.css`) against the app's then
 * `--background` values (`#FFFFFF` / `#0F1729`), converting to linear sRGB
 * and applying the WCAG relative-luminance formula. Each number names the
 * pair:
 *
 * - `text-gray-500` `#6A7282` on `#FFFFFF` **4.84:1**, on `#0F1729` **3.70:1**
 * - `text-gray-400` `#99A1AF` on `#FFFFFF` **2.60:1**, on `#0F1729` **6.88:1**
 * - `text-muted-foreground` `#637083` on `#FFFFFF` **5.03:1**,
 *   `#97A3B4` on `#0F1729` **7.03:1**
 *
 * Cross-checked against what the browser painted on those tokens: on
 * `/?age_min=0&age_max=0.2` in headless Chrome the empty-state element
 * computed to `rgb(99, 112, 131)` on `rgb(255, 255, 255)` (**5.03:1**) and,
 * with `.dark` on the root, `rgb(151, 163, 180)` on `rgb(15, 23, 41)`
 * (**6.99:1**) — the dark figure differing from 7.03 only because the browser
 * quantises the `hsl()` token to 8-bit before compositing.
 *
 * The tokens have since moved to the "Aubergine & Lavender" values
 * (`.claude/PETCONNECT-STYLE-GUIDE.md`, §2), and the argument survives the
 * move because it was about the token, not the hex: `--muted-foreground` is
 * now `#756B88`, **4.9:1** on the white card and about 4.6:1 on the `#F6F4FB`
 * page in light, and `#9C92B4`, **6.9:1** on the `#0F0C18` page in dark — the
 * guide's own table, not re-measured in a browser here. Both still clear
 * 4.5:1, which the two grey literals still do not in one scheme each.
 *
 * So each literal fails AA in exactly one scheme — gray-500 in dark, gray-400
 * badly in light, and gray-400 is 14px, which does not reach the large-text
 * exemption either. The token clears 4.5:1 in both. Parity of the rendered
 * result favours the token; parity of the class string does not.
 * `resources/css/app.css` records the project's standing position on the same
 * trade at `--destructive`, which was overridden rather than ported verbatim:
 * "A contrast failure is a defect, not a design decision."
 *
 * The gradient on `CreatePetButton` used to be the **explicit exception** the
 * user ruled on — ship the legacy violet→fuchsia stops as they are, contrast
 * failure accepted and documented there. The guide retires that gradient
 * along with every other, so the exception is gone and the whole feed now
 * sits on the same side of the trade as this paragraph.
 */
const { pets, nearby } = defineProps<{
    pets: Paginated<PetCard>;
    /** The feed is sorted by distance, which changes what "empty" means. */
    nearby: boolean;
    /** A geolocation request is in flight, so more cards may be coming. */
    locating?: boolean;
}>();

const { t } = useTranslations();

const emptyMessage = computed(() =>
    t(nearby ? 'home.no_nearby_pets' : 'home.no_pets_found'),
);

const isEmpty = computed(() => pets.data.length === 0);
</script>

<template>
    <div class="w-full">
        <InfiniteScroll
            data="pets"
            as="section"
            only-next
            class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <PetListingCard v-for="pet in pets.data" :key="pet.id" :pet="pet" />

            <div
                v-if="isEmpty"
                class="text-muted-foreground col-span-full py-12 text-center"
            >
                <p>{{ emptyMessage }}</p>
            </div>

            <!-- Rendered in the component's own trigger element, below the grid. -->
            <template #next="{ loading, hasMore }">
                <div
                    v-if="loading || locating"
                    class="mt-8 flex justify-center py-4"
                >
                    <Spinner status class="text-primary size-8" />
                </div>

                <div
                    v-else-if="!hasMore && !isEmpty"
                    class="text-muted-foreground mt-8 py-4 text-center text-sm"
                >
                    {{ t('home.end_of_list') }}
                </div>
            </template>
        </InfiniteScroll>
    </div>
</template>
