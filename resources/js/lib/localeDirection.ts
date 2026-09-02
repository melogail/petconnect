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
    if (!locale) {
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
 */
export function initializeLocaleDirection(): void {
    watchEffect(() => apply(currentLocale()));
}
