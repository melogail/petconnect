<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { Check, ChevronDown, MapPin, X } from "@lucide/vue";
import { computed, ref } from "vue";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Spinner } from "@/components/ui/spinner";
import { useGeolocation } from "@/composables/useGeolocation";
import { useTranslations } from "@/composables/useTranslations";
import { home } from "@/routes";
import type { HomeFeedBounds } from "@/types/pets";

/**
 * The feed's location control: "Show near me" on the recency feed, and
 * "Within N km" — the way back out — once the feed is a nearby one.
 *
 * The feed becomes a nearby feed when the query string carries a coordinate
 * pair; `ListHomeFeedRequest` clamps the radius and refuses half a pair, and
 * `PetCardResource` then carries `distance` on every card.
 *
 * The coordinates are never props — the page is told only `nearby` and
 * `radius` — so switching them off writes nulls through `mergeQuery`, which
 * removes the three keys and leaves every filter beside them alone.
 *
 * ## Both halves are deliberate divergences from the legacy app
 *
 * The legacy Home had **no location control at all**, in either direction: it
 * asked the browser for a position on mount and redirected, and once the
 * coordinates were in the query string there was no affordance to take them
 * out again. `Home.vue` still does that same on-mount request.
 *
 * The **clear chip** was the first addition, in phase 3: without it the legacy
 * behaviour traps a visitor in a nearby feed with no exit — every visit
 * re-reads the coordinates from the URL, and nothing on the page offers to
 * drop them. That was a regression the port was not willing to ship.
 *
 * The **"Show near me" button** is the second, added on the user's instruction
 * (2026-09-06). The on-mount request is gated hard on purpose — one attempt
 * per tab, and a refusal memoised for an hour — so a visitor who dismissed the
 * prompt, denied it, or opened the feed in a tab that had already asked, had
 * no way onto the nearby feed until the memo expired. This is that way. It
 * asks with `fresh: true`, which is the **only** caller allowed past a
 * memoised `denied`: a button press is a request to be prompted, where the
 * automatic cycle is not. A browser that refuses again, or has no
 * geolocation at all, gets the status line below rather than a silent
 * no-op — the automatic request fails silently by design, but a press with no
 * visible answer reads as a broken button.
 *
 * The visit it issues is `Home.vue`'s on-mount visit with two differences,
 * both because this is a gesture and that is not: no `replace`, so Back
 * returns to the recency feed the visitor was looking at, and no wait on the
 * deferred `categories` request, which resolved long before anyone could
 * reach this control. (The URL-revert race `Home.vue` documents needs the
 * deferred response to land *after* the redirect; the categories request is
 * dispatched at mount, so a click that beats it is not a realistic window.)
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
 * So both visits name the three props that can differ, exactly as the
 * geolocation visit in `pages/Home.vue` does. Everything else on the page is
 * unchanged by adding or dropping a coordinate pair, and re-sending it would
 * be waste.
 *
 * `reset` still earns its place beside it, for two separate reasons:
 *
 * - `pets` ships from `Inertia::scroll()` with `mergeProps: ['pets.data']`, so
 *   a **partial** visit that re-fetches `pets` appends its page 1 to whatever
 *   is on screen. Now that `only` names `pets`, both visits are exactly that
 *   kind of visit, and without the reset the new page 1 would arrive
 *   underneath the cards it is meant to replace.
 * - It also sets `scrollProps.pets.reset` on the response, which is what
 *   `<InfiniteScroll>` reads to drop its own page cursor. Without it the
 *   scroller keeps counting from where the previous feed left off.
 *
 * The reset names the **prop**, `pets`, not the dotted merge path `pets.data`:
 * the merge is registered under the dotted path but matched on the name, and
 * `reset: ['pets.data']` is a silent no-op that leaves the merge in place —
 * pinned server-side in
 * `tests/Feature/Http/Controllers/Web/HomeControllerTest.php`.
 *
 * ## Measured (the clear half; the button half is read, not run)
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
 *
 * ## Radius choices (2026-10-23)
 *
 * Both halves are now one trigger opening one menu of radii. On the recency
 * feed a choice asks for the location and lands on the nearby feed at that
 * radius; on the nearby feed a choice rewrites only `radius` — the coordinate
 * pair already in the query string is kept by `mergeQuery` — and a last row
 * clears the three keys. The list is a frontend constant unioned with the
 * configured default (so the auto-mount radius is always selectable) and cut
 * to the request's min/max bounds, which is why the component takes the whole
 * `filterBounds` bag. Rows are plain `DropdownMenuItem`s with a leading
 * `Check`, not `DropdownMenuRadioItem`, for the RTL reason recorded in
 * `shell/ShellPreferenceItems.vue`.
 */
const { nearby, radius, bounds } = defineProps<{
    nearby: boolean;
    radius: number | null;
    bounds: HomeFeedBounds;
}>();

const RADIUS_CHOICES_KM = [5, 10, 25, 50, 100];

const { t } = useTranslations();

const { requestLocation } = useGeolocation();

const locating = ref(false);

const failed = ref(false);

const choices = computed(() =>
    [...new Set([...RADIUS_CHOICES_KM, bounds.default_radius_km])]
        .filter(
            (km) => km >= bounds.min_radius_km && km <= bounds.max_radius_km,
        )
        .sort((a, b) => a - b),
);

const triggerLabel = computed(() => {
    if (locating.value) {
        return t("home.locating");
    }

    return nearby
        ? t("home.within_radius", { radius: radius ?? 0 })
        : t("home.show_near_me");
});

const visitOptions = {
    only: ["pets", "nearby", "radius"],
    reset: ["pets"],
    preserveState: true,
    preserveScroll: true,
};

async function select(km: number): Promise<void> {
    if (locating.value) {
        return;
    }

    if (nearby) {
        router.get(
            home.url({ mergeQuery: { radius: km, page: null } }),
            {},
            visitOptions,
        );

        return;
    }

    locating.value = true;
    failed.value = false;

    try {
        const coordinates = await requestLocation({ fresh: true });

        if (!coordinates) {
            failed.value = true;

            return;
        }

        router.get(
            home.url({
                mergeQuery: {
                    latitude: coordinates.latitude,
                    longitude: coordinates.longitude,
                    radius: km,
                    page: null,
                },
            }),
            {},
            visitOptions,
        );
    } finally {
        locating.value = false;
    }
}

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
            only: ["pets", "nearby", "radius"],
            reset: ["pets"],
            preserveScroll: false,
        },
    );
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <!--
            The guide's filter chip (§5.4): `h-10 rounded-[14px] px-3.5
            text-[13px] font-medium`. Lavender `secondary` while a radius is
            applied, `outline` beside the Filters trigger otherwise.
        -->
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    :variant="nearby ? 'secondary' : 'outline'"
                    class="h-10 px-3.5 text-[13px] font-medium"
                    :disabled="locating"
                    :aria-busy="locating"
                >
                    <Spinner v-if="locating" />
                    <MapPin v-else class="size-4" aria-hidden="true" />
                    {{ triggerLabel }}
                    <ChevronDown class="size-4 opacity-60" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start" class="w-44">
                <DropdownMenuItem
                    v-for="km in choices"
                    :key="km"
                    :aria-current="nearby && km === radius ? 'true' : undefined"
                    class="cursor-pointer"
                    @select="select(km)"
                >
                    <Check v-if="nearby && km === radius" class="size-4" />
                    <span v-else class="size-4" aria-hidden="true" />
                    {{ t("home.within_radius", { radius: km }) }}
                </DropdownMenuItem>

                <template v-if="nearby">
                    <DropdownMenuSeparator />

                    <DropdownMenuItem class="cursor-pointer" @select="clear">
                        <X class="size-4" />
                        {{ t("home.clear_nearby_search") }}
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <!--
            One status line, only after a press that produced nothing: the
            automatic request stays silent by design, this one owes an answer.
        -->
        <p
            v-if="failed && !nearby"
            role="status"
            class="text-destructive text-sm"
        >
            {{ t("home.location_unavailable") }}
        </p>
    </div>
</template>
