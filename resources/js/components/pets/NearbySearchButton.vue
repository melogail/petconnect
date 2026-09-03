<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

/**
 * "Within N km" — the way out of a nearby feed.
 *
 * The feed becomes a nearby feed when the query string carries a coordinate
 * pair; `ListHomeFeedRequest` clamps the radius and refuses half a pair, and
 * `PetCardResource` then carries `distance` on every card.
 *
 * The coordinates are never props — the page is told only `nearby` and
 * `radius` — so switching them off writes nulls through `mergeQuery`, which
 * removes the three keys and leaves every filter beside them alone.
 *
 * ## This control is a deliberate divergence from the legacy app
 *
 * The legacy Home had **no location control at all**, in either direction: it
 * asked the browser for a position on mount and redirected, and once the
 * coordinates were in the query string there was no affordance to take them
 * out again. `Home.vue` now does that same on-mount request, which is what
 * removed this component's other half — a "Near me" button that prompts for a
 * location the page has already asked for is a button that does nothing, and
 * the "we could not read your location" line it sat above went with it, since
 * the automatic request is now the thing that fails and it fails silently by
 * design (`useGeolocation` memoises a refusal for an hour rather than nagging).
 *
 * What is left is strictly additive, and it is here on purpose: without it the
 * legacy behaviour traps a visitor in a nearby feed with no exit — every visit
 * re-reads the coordinates from the URL, and nothing on the page offers to
 * drop them. That is a regression this phase is not willing to ship, so the
 * clear chip stays even though the legacy screen has no counterpart to it.
 * It renders only when `nearby` is true, so on the ordinary feed the page
 * looks exactly like the legacy one.
 *
 * ## `only` and `reset` are a pair, and the pair is load bearing
 *
 * `reset` alone does not mean "reset". `@inertiajs/core` builds the partial
 * header from `only.concat(reset)` (`dist/index.js`, `X-Inertia-Partial-Data`
 * is written whenever that concatenation is non-empty), so `reset: ['pets']`
 * with no `only` still sends the visit out as a **partial** one asking for
 * `pets` and nothing else. Through this app's kernel that comes back carrying
 * `errors` and `pets`; `nearby`, `radius`, `filters`, `listingTypes` and
 * `filterBounds` never do, and the client merges the partial over the props it
 * is holding. The URL lost its coordinates and the cards became the recency
 * feed, while `nearby` stayed true — so the heading still read "Nearby Pets"
 * and this chip stayed on screen advertising a radius that no longer applied.
 * The chip looked broken because the only thing it failed to clear was itself.
 *
 * So the visit names the three props that can differ, exactly as the
 * geolocation visit in `pages/Home.vue` does. Everything else on the page is
 * unchanged by dropping a coordinate pair, and re-sending it would be waste.
 *
 * `reset` still earns its place beside it, for two separate reasons:
 *
 * - `pets` ships from `Inertia::scroll()` with `mergeProps: ['pets.data']`, so
 *   a **partial** visit that re-fetches `pets` appends its page 1 to whatever
 *   is on screen. Now that `only` names `pets`, this visit is exactly that
 *   kind of visit, and without the reset the recency page 1 would arrive
 *   underneath the nearby cards it is meant to replace.
 * - It also sets `scrollProps.pets.reset` on the response, which is what
 *   `<InfiniteScroll>` reads to drop its own page cursor. Without it the
 *   scroller keeps counting from where the nearby feed left off.
 *
 * The reset names the **prop**, `pets`, not the dotted merge path `pets.data`:
 * the merge is registered under the dotted path but matched on the name, and
 * `reset: ['pets.data']` is a silent no-op that leaves the merge in place —
 * pinned server-side in
 * `tests/Feature/Http/Controllers/Web/HomeControllerTest.php`.
 *
 * ## Measured
 *
 * At the wire, `artisan serve`, `X-Inertia-Partial-Component: Home` on `/`:
 *
 * - `X-Inertia-Partial-Data: pets` + `X-Inertia-Reset: pets` — the shape a bare
 *   `reset` produces — returns `props` keyed `errors, pets`. That is the defect.
 * - `X-Inertia-Partial-Data: pets,nearby,radius` + the same reset returns
 *   `errors, nearby, pets, radius`.
 * - Both carry `scrollProps.pets.reset: true`, which is the second half of what
 *   the reset is for and is why it is not simply deleted.
 *
 * In the browser (headless Chrome, stylesheet asserted live first, geolocation
 * denied so nothing else can navigate), on
 * `/?latitude=21.4612&longitude=39.2677&radius=20`: heading `Nearby Pets`,
 * chip present, 5 cards. After the click: heading `Discover Pets`, chip
 * **absent**, URL `/`, **12** cards — not 17, so the reset did drop the nearby
 * page rather than letting the recency page append under it. Unchanged 4s
 * later.
 */
const { nearby, radius } = defineProps<{
    nearby: boolean;
    radius: number | null;
}>();

const { t } = useTranslations();

const label = computed(() => t('home.within_radius', { radius: radius ?? 0 }));

/**
 * The accessible name **extends** the visible text rather than replacing it,
 * the convention `card/PetCardActions` records: "Within 20 km" stays a
 * substring, so speech input still matches the words on screen. It is needed
 * because the visible label is a statement, not an action — the `X` that says
 * "clear" is `aria-hidden`, so without this the chip announces as
 * "Within 20 km, button" and nothing conveys what pressing it does.
 */
const accessibleName = computed(() =>
    t('home.clear_nearby_search', { radius: radius ?? 0 }),
);

function clear(): void {
    router.get(
        home.url({
            mergeQuery: {
                latitude: null,
                longitude: null,
                radius: null,
                page: null,
            },
        }),
        {},
        {
            only: ['pets', 'nearby', 'radius'],
            reset: ['pets'],
            preserveScroll: false,
        },
    );
}
</script>

<template>
    <Button
        v-if="nearby"
        variant="secondary"
        :aria-label="accessibleName"
        @click="clear"
    >
        <X class="size-4" aria-hidden="true" />
        {{ label }}
    </Button>
</template>
