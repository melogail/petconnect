import { usePage } from '@inertiajs/vue3';
import { computed, watchEffect, type ComputedRef } from 'vue';
import type { LocaleState } from '@/types/profile';

/**
 * The `locale` shared prop as the client currently holds it.
 *
 * `usePage()` is readable outside a component — the adapter builds it over a
 * module-level ref, not over an injection — but it is empty until
 * `createInertiaApp()` has mounted the `App` component, so unlike every call
 * inside a page the prop is genuinely optional here. Hence the cast: the
 * shared-props type says `locale: LocaleState`, which is true of every page
 * object and false of the instant before the first one exists.
 */
function currentLocale(): LocaleState | undefined {
    const props = usePage().props as { locale?: LocaleState } | undefined;

    return props?.locale;
}

/**
 * The reading direction of the whole client, from the one authority for it.
 *
 * `app.ts` hands this to Reka's `<ConfigProvider :dir>`, which is what every
 * portalled primitive (`DropdownMenu`, `Select`, `Tooltip`, …) reads through
 * `useDirection()`. Nothing else should pass `dir` to a Reka root: a local prop
 * beside this reads as evidence that this does not work.
 *
 * `'ltr'` covers the single render that happens before `App` publishes the
 * first page object; the computed re-evaluates as soon as it does, which is
 * still inside the mount tick and long before any menu can be opened.
 */
export const localeDirection: ComputedRef<LocaleState['direction']> = computed(
    () => currentLocale()?.direction ?? 'ltr',
);

function apply(locale: LocaleState | undefined): void {
    if (!locale || typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    const lang = locale.current.replace('_', '-');

    if (root.lang !== lang) {
        root.lang = lang;
    }

    if (root.dir !== locale.direction) {
        root.dir = locale.direction;
    }
}

/**
 * Keep `<html lang>` and `<html dir>` in step with the `locale` shared prop.
 *
 * `resources/views/app.blade.php` renders both for the first paint, but a
 * language switch is an ordinary Inertia visit: `locale.update` answers with a
 * redirect, the client swaps the page object, and the root template is never
 * re-rendered.
 *
 * This used to hang off `router.on('navigate')` and missed **that** visit —
 * the one visit it exists for. `@inertiajs/core` fires `navigate` only when the
 * swap pushes a history entry (`if (!replace) fireNavigateEvent(...)`), and it
 * sets `replace = replace || isSameUrlWithoutHash(page.url, location)` just
 * above. A locale switch redirects back to the URL you were already on, so the
 * swap replaces rather than pushes and no `navigate` ever fires; the attributes
 * only caught up on the *next* navigation or a hard reload.
 *
 * Watching the page object instead is indifferent to how the visit reached the
 * client — push, replace, back/forward, client visit or partial reload all end
 * in the same assignment — so there is no event taxonomy to keep in step with
 * the router's.
 *
 * ## It must not run on the server, and "must not" here meant "kills the process"
 *
 * `app.ts` calls this at module level, and `app.ts` is also the SSR entry
 * (`@inertiajs/vite` builds the server bundle from it), so this ran once per
 * SSR process. `usePage()` is a **module-level** ref there, shared by every
 * request the process renders. The first render assigned it with the watcher
 * already registered; the watcher re-ran on Vue's scheduler, `apply()` read
 * `document`, and the `ReferenceError` was thrown from a scheduler job —
 * outside the render's `try`, so nothing in `@inertiajs/vite` or
 * `@inertiajs/core`'s server caught it and **the Node process exited**. In
 * development that process is the Vite dev server: every page after the first
 * one rendered took the dev server down with it, and the browser saw
 * `ERR_CONNECTION_RESET` for every module on the next load.
 *
 * Measured 2026-09-06 by POSTing captured page objects to a private dev
 * server's `/__inertia_ssr`: `Home` rendered in 1033ms, the next request
 * (`auth/Login`) died with `localeDirection.ts:41 ReferenceError: document is
 * not defined … at flushJobs`, and the port stopped listening. The built
 * bundle under `inertia:start-ssr` died the same way. `npm run build:ssr`
 * exiting 0 (recorded in `.ai/rules/general.md`) said nothing about this —
 * a build is not a second render.
 *
 * So both halves guard on `document`: the watcher is never registered on the
 * server, and `apply()` bails even if something registers it. The document's
 * `lang` and `dir` for the first paint come from `app.blade.php`, which is
 * why there is nothing for the server to do here.
 */
export function initializeLocaleDirection(): void {
    if (typeof document === 'undefined') {
        return;
    }

    watchEffect(() => apply(currentLocale()));
}
