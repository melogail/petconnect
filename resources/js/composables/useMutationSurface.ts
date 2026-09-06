import { inject, provide, type InjectionKey } from 'vue';

/**
 * The container a write control is mounted in.
 *
 * Every write these controls offer — post, reply, edit, delete, like, report —
 * is an Inertia visit that ends in a redirect back to the current page. On a
 * page that renders the thing being written that is exactly right: the page
 * re-renders and its props come back fresh. Inside a dialog mounted on a feed
 * card it is not, for two reasons that are properties of the *page*, not of the
 * control:
 *
 * 1. **`Home`'s `pets` prop is an `Inertia::scroll()` merge prop.** A full
 *    visit replaces it, so a reader who has scrolled to page 4 is dropped back
 *    to page 1. A partial reload of it would be worse: `@inertiajs/core`'s
 *    `mergeProps()` runs only `if (this.requestParams.isPartial())`, and
 *    `ScrollProp` sets no `matchPropsOn` unless the controller calls
 *    `->matchOn()`, so the incoming page-1 array is **appended** to the one
 *    already on screen and every card duplicates. That is what legacy's
 *    `only: ['pets','flash']` would do here; it was safe there only because
 *    that feed was not a merge prop.
 * 2. **A full visit remounts the page component**, which unmounts the dialog
 *    mid-conversation.
 *
 * So a surface says what the visit should ask for and what to do once it
 * lands. The default — no provider, i.e. a page rendering its own props —
 * changes nothing about how these controls have always behaved.
 *
 * `visit` is spread into the visit options after `preserveScroll`, so a
 * surface can override that too. `onMutated` is called on success and is where
 * a surface that owns its own copy of what was written refetches it.
 *
 * ## Why this is a composable and not a `lib/` module
 *
 * It is the only `provide`/`inject` pair in the codebase: it holds
 * cross-component state for the lifetime of a subtree, which is what a
 * composable is, while `resources/js/lib/**` is pure functions. The name is
 * deliberately not about comments — `components/reports/ReportDialog.vue` is
 * mounted by a profile **review** card as well as by a comment row, and it
 * reads this.
 */
export type MutationSurface = {
    /** Merged into the Inertia visit every mutation issues. */
    visit: {
        only?: string[];
        preserveState?: boolean;
    };
    /** Called after a mutation succeeds, so the surface can refresh itself. */
    onMutated: () => void;
};

/**
 * A page that renders its own content from its own props: plain visits,
 * nothing to refresh by hand. `pages/pets/Show.vue` and `pages/profile/Show.vue`
 * are the ones today.
 */
const PAGE_SURFACE: MutationSurface = {
    visit: {},
    onMutated: () => {},
};

const mutationSurfaceKey = Symbol(
    'mutation-surface',
) as InjectionKey<MutationSurface>;

export function provideMutationSurface(surface: MutationSurface): void {
    provide(mutationSurfaceKey, surface);
}

export function useMutationSurface(): MutationSurface {
    return inject(mutationSurfaceKey, PAGE_SURFACE);
}
