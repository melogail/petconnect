<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import CreatePetButton from '@/components/pets/CreatePetButton.vue';
import NearbySearchButton from '@/components/pets/NearbySearchButton.vue';
import PetFeed from '@/components/pets/PetFeed.vue';
import PetFilterSheet from '@/components/pets/PetFilterSheet.vue';
import { safeGet, safeSet, useGeolocation } from '@/composables/useGeolocation';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';
import type {
    HomeFeedBounds,
    HomeFeedFilters,
    Paginated,
    PetCard,
    PetCategoryOption,
    PetListingType,
    SelectOption,
} from '@/types';

/**
 * The public discovery feed.
 *
 * Two of the props are not plain props and the page is built around that:
 *
 * - `pets` comes from `Inertia::scroll()`. The payload carries
 *   `mergeProps: ["pets.data"]` and the scroll cursor, so `<InfiniteScroll>`
 *   inside `PetFeed` appends page 2 into the list already on screen. The
 *   scroller owns that cycle; the **one** place this file touches `pets` is the
 *   geolocation redirect below, which names it in `only` and pairs that with a
 *   `reset` for exactly the reason the merge makes necessary.
 * - `categories` comes from `Inertia::defer()`. It is announced in
 *   `deferredProps` and fetched by the router in one follow-up request, so the
 *   filter sheet gates on `<Deferred>` and the page issues no `onMounted`
 *   fetch. It stays `undefined` until that request lands.
 *
 * ## `CreatePetButton` is the only publish control in the application
 *
 * The legacy application put this control **on Home** and had **no such button
 * in the navbar**. Phase 1 added one to `PublicHeader` anyway, on the grounds
 * that the header was then the only chrome linking `pets.create` at all;
 * restoring the legacy Home CTA in phase 3 made the two coexist, and the user
 * then ruled for exact legacy parity. The header's button is gone.
 *
 * So the button in the heading row below is now the only link to `pets.create`
 * outside two on `pages/Help.vue` — `pages/pets/Create.vue:46` holds a third
 * `create()` reference, but it is that page's own breadcrumb self-link, not an
 * entry point. It gates on `email_verified_at` rather than on `auth.user`,
 * which is the correct predicate: `pets.create` sits behind
 * `['auth', 'verified']`.
 *
 * ## The feed makes itself a nearby feed, once — and offers a button after that
 *
 * The legacy screen had no location control: it asked the browser for a
 * position on mount and, if it was given one, re-visited itself with the
 * coordinates in the query string. This page does the same. What that costs is
 * a permission prompt on a first visit, so the cycle is gated three ways —
 * `useGeolocation` remembers a refusal for an hour and a position for five
 * minutes, and the `sessionStorage` visit key below holds it to at most one
 * attempt per tab whatever the composable would allow.
 *
 * Those gates are also why `NearbySearchButton` carries a "Show near me"
 * control on the recency feed — a deliberate addition to legacy, made on the
 * user's instruction (2026-09-06). Without it a visitor who dismissed the
 * prompt, or whose tab had already spent its one attempt, had no way onto the
 * nearby feed at all. The button asks with `fresh: true`, so it is the one
 * path that re-prompts past a memoised refusal, and it issues the same
 * `only` / `reset` visit this file does below. The automatic cycle is
 * untouched by it.
 *
 * The visit key is claimed **before** the await, not after. `requestLocation`
 * stays open for as long as the visitor leaves the browser prompt up
 * (`timeout: 10_000`), and applying a filter in the meantime remounts this
 * page; claiming the slot on the way out would let that remount raise a second
 * prompt and fire a second redirect.
 *
 * It is claimed on the **nearby** path too, and that ordering is the whole
 * point of writing it before the `nearby` bail-out rather than after. Being
 * served a nearby feed already answers "has this page asked?". The bug this
 * closes: open a shared `?latitude=…` URL in a fresh tab, so the key is unset,
 * then press the clear chip. Home remounts with `nearby` false and, under the
 * old order, with the key still unset — `requestLocation()` fires, resolves
 * with no prompt because the origin's permission grant persists, and puts the
 * visitor straight back on the nearby feed. The chip looked dead. The ordinary
 * path only ever worked because the key happened to have been claimed by an
 * earlier mount, which was load-bearing coupling nothing recorded.
 *
 * Measured over CDP with the origin's geolocation permission granted and a
 * position override standing in for a real fix. Fresh tab on
 * `/?latitude=21.4612&longitude=39.2677&radius=20`: the key reads `1`
 * immediately after mount, the URL is unchanged 4s later, and after the chip is
 * pressed the page settles on `/` with the heading `Discover Pets` and stays
 * there for a further 6s — no bounce back. A control run in a second fresh tab
 * confirms the ordinary path is untouched: a cold `/` with the same grant
 * still redirects to the nearby URL within 4s.
 *
 * `reset: ['pets']` on that visit is the one thing here that is not obvious.
 * The visit is partial (`only` names `pets`) and `pets` is a merge prop, so
 * re-fetching it **appends**: without the reset, the nearby page 1 arrives
 * underneath the cards the recency feed already rendered and the visitor sees
 * one feed sorted two ways. The reset also drops `<InfiniteScroll>`'s page
 * cursor, so the nearby feed starts counting from page 1 again. It names the
 * prop, `pets`, not the dotted merge path `pets.data`, which is a silent no-op
 * — both halves of that contract are pinned server-side in
 * `tests/Feature/Http/Controllers/Web/HomeControllerTest.php`.
 *
 * ## Every string this file and `PetFeed` render comes from the catalogue
 *
 * `t()` against keys in `lang/{locale}.json`, both catalogues in step
 * (.ai/rules/lang.md). `<Head title>` included, as of phase 3 — `home.title`
 * now exists in both, matching what `Help.vue` and `Support.vue` already do,
 * because an English `<title>` is what an Arabic reader gets in their tab and
 * their bookmarks.
 *
 * That claim is **scoped to this file, `PetFeed`, `CreatePetButton` and
 * `NearbySearchButton` — the screen as a whole is not yet fully covered**, and
 * the heading above must not be read as saying otherwise. `PetFilterSheet`,
 * which this page mounts, shipped in phase 3a with nine hardcoded English
 * labels and was translated by the concurrent rewrite of that component — it
 * now calls `t()` for at least the eight it owns (`home.filters`,
 * `home.clear_all`, `home.animal_type`, `home.age_range`,
 * `home.adoption_type`, `home.vaccination_status`, `home.vaccinated_only`,
 * `home.apply_filters`), with the `components/pets/filter/*` children that
 * rewrite extracted translating the rest. The card itself went through `t()`
 * for its visible strings when it was redrawn after legacy on 2026-09-06
 * (`card/*`: the pills, gender, age, "Read more", "View Details"). Still
 * outstanding on this screen, all inside the card and none of it this file's
 * to fix: `card/PetCardActions` builds literal English accessible names
 * (`Like :name, N likes`, `Message :name about :pet`, the `card/labels`
 * count helper), and
 * `messaging/StartConversationButton` is untranslated throughout — the literal
 * `Message` on its trigger, and its dialog title, description, field label and
 * submit button with it.
 */
const { nearby, filterBounds, categories } = defineProps<{
    pets: Paginated<PetCard>;
    filters: HomeFeedFilters;
    nearby: boolean;
    radius: number | null;
    listingTypes: SelectOption<PetListingType>[];
    filterBounds: HomeFeedBounds;
    categories?: PetCategoryOption[];
}>();

/**
 * One geolocation cycle per tab. Deliberately not folded into
 * `useGeolocation`, which answers "am I allowed to ask?" — this answers "has
 * this page already asked?", and the two expire on different clocks.
 */
const LOCATION_VISIT_KEY = 'petconnect:home-location-visit';

const { t } = useTranslations();

const { requestLocation } = useGeolocation();

const heading = computed(() =>
    t(nearby ? 'home.nearby_pets' : 'home.discover_pets'),
);

/**
 * The whole locate-then-redirect cycle is in flight, so a different feed may
 * be a moment away and `PetFeed` should keep its spinner up.
 *
 * A local ref rather than `status.value === 'pending'`, which was the previous
 * shape and covered the wrong interval: `status` flips to `granted` the instant
 * the position resolves, while `untilCategoriesResolve()` then blocks for up to
 * `DEFERRED_SETTLE_TIMEOUT_MS` before the redirect is even dispatched. That
 * left the feed sitting idle for seconds with nothing to say it was about to
 * be replaced. The legacy page held its own flag until the `router.get` went
 * out, and so does this.
 *
 * Measured over CDP against the running application, position supplied by
 * `Emulation.setGeolocationOverride` and the page sampled every 25ms. With the
 * deferred `categories` request held at the network layer for **1500ms**
 * (`Fetch.requestPaused`, matched on its `X-Inertia-Partial-Data: categories`
 * header), the spinner is up from t=**234ms** to t=**1825ms** — 1591ms, the
 * whole hold — and the nearby URL lands at t=**1937ms**. On the old flag the
 * spinner would have gone down at roughly 250ms, when the position resolved,
 * leaving ~1.6s of dead air. Unthrottled the whole cycle is 211→275ms with the
 * URL at 369ms, so the residual gap is the redirect's own round trip: the flag
 * is cleared when the visit is dispatched, not when it returns.
 */
const isLocating = ref(false);

/** How long to hold the redirect for the deferred request below. */
const DEFERRED_SETTLE_TIMEOUT_MS = 3000;

/**
 * Still on this page. Both awaits below can outlive it — the location prompt
 * by as much as ten seconds — and a redirect that lands after the visitor has
 * moved on drags them back here.
 */
let mounted = true;

onUnmounted(() => {
    mounted = false;
});

/**
 * Wait for the deferred `categories` request to land, or give up.
 *
 * This is not politeness, it is a race that was **measured**. Inertia issues
 * the deferred-prop request at mount, against the URL the page was mounted on
 * — `/`, with no coordinates — and `page.url` is set from whichever response
 * lands last. A redirect fired at mount is dispatched alongside it (measured
 * over CDP's network log: the feed visit at `Network.requestWillBeSent`
 * t=13046.719, `?latitude=…&longitude=…&radius=20`, and `X-Inertia-Partial-
 * Data: categories` to `/` at t=13046.727, 8ms later), so the categories
 * response overwrites the address bar back to `/` while the nearby feed stays
 * on screen. Traced through a `history.pushState` hook: `replace` to the
 * nearby URL at t=377ms, then `push` to `/` at t=419ms, both from Inertia's
 * own history wrapper.
 *
 * The props survive — the deferred response carries only `categories` — so the
 * only casualty is the URL, and the URL is where this feed keeps its state:
 * `PetFilterSheet` and `NearbySearchButton` both build their next visit with
 * `mergeQuery`, off `window.location.search`. Losing the coordinates there
 * means applying a filter, or reloading, silently drops the visitor back to
 * the recency feed. Legacy had no deferred props and so no such race.
 *
 * Waiting is what makes the order deterministic. Without it the winner is
 * whichever query finishes first — the feed's eleven queries or the category
 * list's one — which is a coin toss on a production connection pool and was
 * simply always wrong on the single-threaded `artisan serve` this was measured
 * against.
 *
 * ## What the timeout does and does not cover
 *
 * It covers the **failed** deferred request: a redirect that is three seconds
 * late instead of one that never happens. It does **not** cover a merely slow
 * one. A categories response that takes longer than
 * `DEFERRED_SETTLE_TIMEOUT_MS` lands after the redirect has gone out and
 * reintroduces the exact race this wait was built to close — the nearby feed
 * on screen, the address bar back at `/`.
 *
 * That gap is known and accepted, not overlooked — but the choice is not the
 * two-way one this note used to claim. There are three options:
 *
 * 1. **Redirect once the wait is over, whatever landed** (what this does). A
 *    categories response slower than the timeout can still revert the URL. That
 *    is recoverable: reloading or re-applying a filter puts the visitor on the
 *    recency feed, which is where they started.
 * 2. **Gate the redirect on `categories !== undefined` after the wait.** Trades
 *    the uncovered case for a worse one: on a *failed* deferred request the
 *    nearby feed never arrives at all, and the session visit key is already
 *    spent, so the tab never retries. A feature that silently never fires is
 *    worse than a URL that occasionally reverts.
 * 3. **Gate as in (2), and release `LOCATION_VISIT_KEY` on the give-up path**,
 *    handing the one-shot slot back so the next mount retries. This is what
 *    makes (2) survivable, and it is why (2) cannot be waved away as strictly
 *    worse — the earlier version of this paragraph did exactly that and was
 *    overstating its case.
 *
 * (3) is not implemented, and the reason is worth recording because it is not
 * obvious: the visit key does two jobs. It answers "has this page asked?" and —
 * because it is claimed before the `nearby` bail-out — also "has this tab
 * already been offered a nearby feed?". So releasing it is only safe on a path
 * that did **not** redirect. Release it after a redirect has gone out and the
 * dead-clear-chip bug documented above comes straight back: press clear, Home
 * remounts with `nearby` false and the key unset, the cached grant resolves
 * with no prompt, and the visitor is bounced onto the nearby feed again. Under
 * (1) every give-up path after the coordinates arrive either redirects or means
 * the page is already gone, so there is nowhere safe to put the release. It
 * becomes available only if (2) is adopted together with it, as one change —
 * which is a behaviour decision this remediation round did not have a mandate
 * to take, not a case that was missed.
 *
 * Three seconds is set against the measured spread (the two requests left 8ms
 * apart, resolving 42ms apart), so it is two orders of magnitude of headroom
 * over the case that was actually observed.
 */
function untilCategoriesResolve(): Promise<void> {
    if (categories !== undefined) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        const timer = setTimeout(finish, DEFERRED_SETTLE_TIMEOUT_MS);
        const stop = watch(() => categories, finish);

        function finish(): void {
            clearTimeout(timer);
            stop();
            resolve();
        }
    });
}

onMounted(async () => {
    // `safeGet` / `safeSet`, never the bare global and never a `Storage`
    // handle: the getter throws outright under some privacy modes, `setItem`
    // throws under storage pressure even when the getter does not, and this
    // file is server-rendered besides. See `useGeolocation.ts`.
    if (safeGet(LOCATION_VISIT_KEY)) {
        return;
    }

    // Claimed before the `nearby` bail-out, deliberately. See the docblock.
    safeSet(LOCATION_VISIT_KEY, '1');

    if (nearby) {
        return;
    }

    isLocating.value = true;

    try {
        const coordinates = await requestLocation();

        if (!coordinates) {
            return;
        }

        await untilCategoriesResolve();

        if (!mounted) {
            return;
        }

        router.get(
            home.url({
                mergeQuery: {
                    latitude: coordinates.latitude,
                    longitude: coordinates.longitude,
                    radius: filterBounds.default_radius_km,
                    page: null,
                },
            }),
            {},
            {
                // `mergeQuery` carries the applied filters over from the
                // address bar, so these three are the only props that can
                // differ. `categories` is deliberately absent: it is a deferred
                // prop the client already holds, and naming it here would
                // resolve it again.
                only: ['pets', 'nearby', 'radius'],
                reset: ['pets'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    } finally {
        // Cleared once the visit is dispatched, not once the position lands.
        isLocating.value = false;
    }
});
</script>

<template>
    <div class="mx-auto w-full max-w-7xl px-6 py-8">
        <Head :title="t('home.title')" />

        <!-- The visible heading is an h2, so the page still owes the document an h1. -->
        <h1 class="sr-only">{{ heading }}</h1>

        <div class="mb-6 flex flex-wrap items-center gap-2">
            <PetFilterSheet
                :filters="filters"
                :bounds="filterBounds"
                :listing-types="listingTypes"
                :categories="categories"
            />

            <NearbySearchButton
                :nearby="nearby"
                :radius="radius"
                :default-radius="filterBounds.default_radius_km"
            />
        </div>

        <div class="w-full">
            <div
                class="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
            >
                <h2
                    class="text-primary dark:text-primary-400 text-2xl font-bold"
                >
                    {{ heading }}
                </h2>

                <CreatePetButton />
            </div>

            <PetFeed :pets="pets" :nearby="nearby" :locating="isLocating" />
        </div>
    </div>
</template>
