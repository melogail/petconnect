import { computed, onUnmounted, ref, type ComputedRef } from 'vue';

/**
 * Where a location request got to.
 *
 * `denied` and `unsupported` are the two terminal ones — they are remembered
 * and never retried inside the memo window. `timeout` and `unavailable` are
 * not: they describe a device that was willing but could not answer, so the
 * next mount is allowed to ask again.
 *
 * Allowed **by this module**, which is not the same as retried. The only
 * caller, `pages/Home.vue`, is stricter: its one-shot `LOCATION_VISIT_KEY`
 * stops a tab asking twice whatever the status was, so in practice a `timeout`
 * is not re-attempted either. If that retry is ever wanted, the visit key is
 * where to relax it — not here.
 */
export type GeolocationStatus =
    | 'idle'
    | 'pending'
    | 'granted'
    | 'denied'
    | 'unavailable'
    | 'timeout'
    | 'unsupported';

export type UserCoordinates = {
    latitude: number;
    longitude: number;
};

export type UseGeolocationReturn = {
    /** Where the request got to. `pending` is "the browser prompt is up". */
    status: ComputedRef<GeolocationStatus>;
    /** Resolve the viewer's position, or `null` if it cannot be had. */
    requestLocation: () => Promise<UserCoordinates | null>;
};

type StoredLocation = UserCoordinates & { obtainedAt: number };

type BlockedStatus = Extract<GeolocationStatus, 'denied' | 'unsupported'>;

type StoredPermission = { status: BlockedStatus; storedAt: number };

const COORDINATES_KEY = 'petconnect:user-coordinates';
const PERMISSION_KEY = 'petconnect:geolocation-permission';

/** A position is reusable for five minutes; after that, ask again. */
const COORDINATES_TTL_MS = 5 * 60 * 1000;

/** A refusal is remembered for an hour, so a reload does not re-prompt. */
const PERMISSION_TTL_MS = 60 * 60 * 1000;

const GEO_OPTIONS: PositionOptions = {
    enableHighAccuracy: true,
    timeout: 10_000,
    maximumAge: 300_000,
};

/**
 * `sessionStorage`, or nothing. Deliberately **not** exported: every access
 * this module and its callers make goes through `safeGet` / `safeSet` /
 * `safeRemove` below, so nobody ever holds a bare `Storage` to call an
 * unguarded method on.
 *
 * Three different ways a bare reference goes wrong, and this file is where all
 * of them are answered:
 *
 * 1. **No `window` at all.** The legacy composable touched `sessionStorage`
 *    directly at call time, which was safe there because that app had no
 *    server renderer. This one is imported by `pages/Home.vue`, which **is**
 *    server-rendered (`npm run build:ssr`, and `@inertiajs/vite` renders in dev
 *    too), and `setup()` runs on the server — where a bare `sessionStorage`
 *    reference is a `ReferenceError`, not `undefined`, and takes the whole page
 *    render down.
 *
 *    Established by rendering both shapes through `vue/server-renderer` under
 *    Node, not by reading the spec: a component whose `setup()` calls
 *    `useGeolocation()` renders `<p>idle</p>`, while the same setup written the
 *    legacy way — `sessionStorage.getItem(...)` at call time — throws
 *    `ReferenceError: sessionStorage is not defined`. `config('inertia.ssr')`
 *    is enabled and `bootstrap/ssr/app.js` is built, so that renderer is this
 *    app's.
 *
 * 2. **A `window` whose `sessionStorage` getter throws.** Some privacy modes
 *    and third-party-cookie blocks answer the property read with a
 *    `SecurityError` rather than with `undefined`, so the guard has to be a
 *    `try`, not a truthiness check.
 *
 * 3. **A getter that resolves, whose `setItem` then throws.** Safari Private
 *    Browsing and any device under storage pressure hand back a live `Storage`
 *    and reject the write with `QuotaExceededError`. This is the case a guard
 *    around the property read alone does **not** cover, and it was live in this
 *    file until 2026-09-03: `storeCoordinates` / `storePermission` called
 *    `setItem` on the accessor's result, so the throw escaped the
 *    `getCurrentPosition` callback past `resolve()`, the promise never settled,
 *    and `Home.vue`'s `await` hung with its one-shot session slot already
 *    burned.
 *
 * So the rule is not "the getter can throw" but **any access, read or write,
 * can throw** — and every one of them is funnelled through the three helpers
 * below, which are symmetrical on purpose: a future reader cannot guard the
 * read path and forget the write path, because there is no unguarded path left
 * to reach.
 *
 * Measured, not reasoned, in both shapes. Directly on this module under Node
 * (`node --experimental-strip-types`, a stubbed `navigator.geolocation`), on
 * `resources/js/composables/useGeolocation.ts`:
 *
 * - **2026-09-03**, `setItem` throwing `QuotaExceededError` with the getter
 *   working normally: `requestLocation()` resolves `null` on a refusal and
 *   `{ latitude: 1.5, longitude: 2.5 }` on a grant, over 2 rejected writes.
 *   The same script against the pre-fix file — guard around the property read
 *   only — settles neither call within 800ms and reports the
 *   `QuotaExceededError` arriving at `process.on('uncaughtException')`, which
 *   is the escape past `resolve()` described above.
 * - **2026-09-03**, a getter defined to throw `SecurityError` on every read:
 *   the same two resolutions, over 9 throwing reads. This is a re-run — the
 *   first was **2026-09-02** against the revision before the helpers, where the
 *   same two resolutions came over 10 throwing reads (the count moved because
 *   `readFresh` no longer holds a `Storage` across two calls), and where a copy
 *   with the accessor's `try` removed died inside `storePermission` instead of
 *   reaching either assertion.
 *
 * And in the running application, the throw installed via
 * `Page.addScriptToEvaluateOnNewDocument` so it is live before any app code,
 * the page sampled every 250ms for 6s:
 *
 * - **2026-09-02**, throwing getter. `/` renders (`Discover Pets`, 12 cards),
 *   no uncaught exception reaches the page, the feed spinner is down at every
 *   sample — i.e. `Home.vue`'s `onMounted` ran to completion — and with the
 *   permission granted the page still redirects to the nearby feed.
 * - **2026-09-03**, `window.sessionStorage.setItem` shadowed with a throwing
 *   own property, getter and `getItem` left alone, against `npm run build`
 *   output on `http://127.0.0.1:8931` (`php artisan serve`). Geolocation
 *   granted with a position override: `?latitude=…&longitude=…&radius=20` at
 *   **500ms**, heading `Nearby Pets`, spinner down from the 500ms sample on,
 *   two rejected `setItem` calls, no uncaught exception. Denied: stays on `/`,
 *   `Discover Pets`, **12 cards**, spinner down, no uncaught exception.
 *   **Control**, the same build with `safeSet` / `safeRemove` stripped back to
 *   bare `sessionStore()?.setItem(…)`: with the identical grant and override
 *   the page never leaves `/` and never redirects, because the visit-key write
 *   at the top of `onMounted` throws and the hook is abandoned there. The
 *   feature is simply dead.
 */
function sessionStore(): Storage | null {
    try {
        return typeof window === 'undefined' ? null : window.sessionStorage;
    } catch {
        return null;
    }
}

/**
 * A stored string, or `null` — for a missing key, an absent store, or a store
 * that refused the read. All three mean the same thing to every caller here:
 * no cached value, ask again.
 */
export function safeGet(key: string): string | null {
    try {
        return sessionStore()?.getItem(key) ?? null;
    } catch {
        return null;
    }
}

/**
 * Write, or silently do not.
 *
 * Failing silently is the correct answer rather than a swallowed bug: every
 * read is TTL-checked and treats a missing value as "ask again", so a store
 * that will not take writes degrades to no cache at all. It costs a repeated
 * permission prompt, never a wrong position — and, unlike letting the throw
 * out, it costs no hung promise.
 */
export function safeSet(key: string, value: string): void {
    try {
        sessionStore()?.setItem(key, value);
    } catch {
        // See above: an unwritable store is a cache miss, not a failure.
    }
}

/** Remove, or silently do not. Same reasoning as `safeSet`. */
export function safeRemove(key: string): void {
    try {
        sessionStore()?.removeItem(key);
    } catch {
        // See `safeSet`.
    }
}

/**
 * Read a JSON value written by this module, discarding it once it has aged out.
 *
 * The `try` here covers `JSON.parse` and the `writtenAt` read only — the store
 * access is already guarded inside `safeGet` / `safeRemove`. The value is a
 * string another tab or an older build of this file may have written, so it is
 * genuinely untrusted input. Either way the answer is "no cached value", which
 * is the safe one — it costs a prompt, never a wrong position.
 */
function readFresh<T>(
    key: string,
    writtenAt: (value: T) => number,
    ttl: number,
): T | null {
    const raw = safeGet(key);

    if (!raw) {
        return null;
    }

    try {
        const parsed = JSON.parse(raw) as T;

        if (Date.now() - writtenAt(parsed) > ttl) {
            safeRemove(key);

            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

function readStoredCoordinates(): UserCoordinates | null {
    const stored = readFresh<StoredLocation>(
        COORDINATES_KEY,
        (value) => value.obtainedAt,
        COORDINATES_TTL_MS,
    );

    return stored
        ? { latitude: stored.latitude, longitude: stored.longitude }
        : null;
}

/**
 * Cache a position, and clear the refusal memo with it: a grant is the most
 * recent answer the viewer gave, so a stale `denied` must not outlive it.
 */
function storeCoordinates(coordinates: UserCoordinates): void {
    const payload: StoredLocation = { ...coordinates, obtainedAt: Date.now() };

    safeSet(COORDINATES_KEY, JSON.stringify(payload));
    safeRemove(PERMISSION_KEY);
}

function readStoredPermission(): BlockedStatus | null {
    return (
        readFresh<StoredPermission>(
            PERMISSION_KEY,
            (value) => value.storedAt,
            PERMISSION_TTL_MS,
        )?.status ?? null
    );
}

function storePermission(status: BlockedStatus): void {
    const payload: StoredPermission = { status, storedAt: Date.now() };

    safeSet(PERMISSION_KEY, JSON.stringify(payload));
}

/**
 * A `GeolocationPositionError` code, as one of our statuses.
 *
 * The codes are read off the error instance rather than off the
 * `GeolocationPositionError` global, because that global does not exist in a
 * Node SSR context and this file is loaded there.
 */
function mapGeolocationError(
    error: GeolocationPositionError,
): GeolocationStatus {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            return 'denied';
        case error.TIMEOUT:
            return 'timeout';
        default:
            return 'unavailable';
    }
}

/**
 * The viewer's position, asked for at most once per short-lived session cache.
 *
 * Ported from the legacy app's composable of the same name. `pages/Home.vue` is
 * the only caller: the feed sorts by distance when the query string carries a
 * coordinate pair, and this is what produces that pair without a button for the
 * visitor to press.
 *
 * ## Two caches, two different jobs
 *
 * - **Coordinates**, `petconnect:user-coordinates`, five minutes. A position is
 *   expensive (a GPS fix, and a permission prompt the first time) and does not
 *   move far in five minutes, so a second visit inside the window reuses it and
 *   prompts nobody. Session-scoped, so closing the tab forgets it — a location
 *   is not something to persist past the visit that needed it.
 * - **A refusal**, `petconnect:geolocation-permission`, one hour. This is the
 *   one that matters: without it, "ask on mount" means a visitor who said no is
 *   asked again on the next page load, and again after that. Only `denied` and
 *   `unsupported` are memoised. A `timeout` or an unavailable fix is not a
 *   refusal, so it is left to be retried.
 *
 * The two are kept apart because they expire on different clocks and a grant
 * has to invalidate a stored refusal (see `storeCoordinates`). One combined
 * record would have to pick one TTL for both.
 *
 * ## What it does not do
 *
 * It does not navigate, and it does not know what the coordinates are for. The
 * caller decides — `Home.vue` gates the whole cycle behind a `sessionStorage`
 * visit key of its own so a remount cannot start a second one, which is a
 * different concern from "have I already been refused" and deliberately not
 * folded in here.
 *
 * `coordinates` is not exposed as state. `requestLocation()` resolves with them
 * and the cache is what survives a remount, so a public ref would be a second
 * copy with no reader.
 *
 * `cancelled` makes a resolution that lands after the caller has gone away a
 * no-op rather than a write into a dead component. `getCurrentPosition` has no
 * abort, so the promise cannot be cancelled — only its effect can. It gates the
 * two `ref` writes and the resolved value; it deliberately does **not** gate
 * the coordinate cache, which is module state the next mount reads. See the
 * comment in the success callback.
 */
export function useGeolocation(): UseGeolocationReturn {
    const status = ref<GeolocationStatus>('idle');
    const coordinates = ref<UserCoordinates | null>(readStoredCoordinates());

    let cancelled = false;

    if (coordinates.value) {
        status.value = 'granted';
    } else {
        status.value = readStoredPermission() ?? 'idle';
    }

    onUnmounted(() => {
        cancelled = true;
    });

    function requestLocation(): Promise<UserCoordinates | null> {
        if (cancelled) {
            return Promise.resolve(null);
        }

        if (coordinates.value) {
            status.value = 'granted';

            return Promise.resolve(coordinates.value);
        }

        const blocked = readStoredPermission();

        if (blocked) {
            status.value = blocked;

            return Promise.resolve(null);
        }

        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            status.value = 'unsupported';
            storePermission('unsupported');

            return Promise.resolve(null);
        }

        status.value = 'pending';

        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const next: UserCoordinates = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                    };

                    // Cached before the `cancelled` check, deliberately, and
                    // this is a divergence from the legacy composable. The
                    // cache is a module-level side effect with no component
                    // behind it, so there is nothing dead to write into. The
                    // legacy order threw a granted position away whenever the
                    // caller unmounted while the prompt was up — apply a filter
                    // at that moment and `Home.vue` remounts, cancelling this
                    // instance — and `Home.vue` has already burned its one
                    // visit key by then, so the tab never asks again and the
                    // visitor who said yes never sees the nearby feed. Caching
                    // first turns that into a cache hit on the remount.
                    storeCoordinates(next);

                    if (cancelled) {
                        resolve(null);

                        return;
                    }

                    coordinates.value = next;
                    status.value = 'granted';

                    resolve(next);
                },
                (error) => {
                    if (cancelled) {
                        resolve(null);

                        return;
                    }

                    const failure = mapGeolocationError(error);

                    status.value = failure;

                    if (failure === 'denied') {
                        storePermission('denied');
                    }

                    resolve(null);
                },
                GEO_OPTIONS,
            );
        });
    }

    return {
        status: computed(() => status.value),
        requestLocation,
    };
}
