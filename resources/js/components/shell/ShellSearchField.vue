<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';
import type { HomeFeedFilters } from '@/types';

/**
 * The search box in the middle of the public header.
 *
 * It drives the home feed's `search` filter — name, breed or category, see
 * `BuildHomeFeed\ApplySearchFilter` — and nothing else, so it is one input in
 * a form and a submit on Enter. There is no as-you-type request: the feed is
 * paginated and infinite-scrolled, and a visit per keystroke would race its
 * own pages.
 *
 * ## Two kinds of visit, decided by which page is up
 *
 * On the home page the search is one more feed filter, so it goes out the way
 * `PetFilterSheet` applies one: `mergeQuery` keeps every other filter and the
 * nearby coordinates from the address bar, `page` is dropped, and the visit is
 * partial with `reset: ['pets']` beside `only: ['pets', 'filters']` — the
 * `pets` prop is a merge prop, and without the reset a fresh search would
 * append to the list already on screen. That sheet's docblock owns the long
 * version of why the two options travel together.
 *
 * From any other page it is a plain visit to the home route carrying only the
 * term: a partial reload names props of a component that is not mounted, and
 * the address bar's query belongs to the page being left.
 *
 * ## Seeded from the feed, cleared through the feed
 *
 * The box shows the term the feed is currently narrowed by (`filters.search`
 * on the home page's props) so a reload or a shared link reads right. Emptying
 * it while a search is applied — the native clear control on `type="search"`,
 * or backspacing it out — drops the filter straight away rather than waiting
 * for an Enter on an empty box, which nobody presses.
 */
const { t } = useTranslations();
const page = usePage();

/** The term the feed is narrowed by, when the feed is the page on screen. */
function appliedSearch(): string | null {
    const filters = page.props.filters as HomeFeedFilters | undefined;

    return filters?.search ?? null;
}

const term = ref(appliedSearch() ?? '');

watch(
    () => appliedSearch(),
    (applied) => {
        term.value = applied ?? '';
    },
);

function submit(): void {
    const search = term.value.trim();
    const value = search === '' ? null : search;

    if (page.component === 'Home') {
        router.get(
            home.url({ mergeQuery: { search: value, page: null } }),
            {},
            {
                only: ['pets', 'filters'],
                reset: ['pets'],
                preserveScroll: false,
            },
        );

        return;
    }

    router.get(home.url(value === null ? undefined : { query: { search: value } }));
}

watch(term, (value) => {
    if (value.trim() === '' && appliedSearch() !== null) {
        submit();
    }
});
</script>

<template>
    <form role="search" class="relative" @submit.prevent="submit">
        <input
            v-model="term"
            type="search"
            name="search"
            maxlength="100"
            autocomplete="off"
            enterkeyhint="search"
            :placeholder="t('nav.search_placeholder')"
            :aria-label="t('nav.search_placeholder')"
            class="border-border bg-card text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-ring/30 h-11 w-full rounded-[14px] border ps-11 pe-4 text-sm transition-[border-color,box-shadow] duration-200 ease-out focus:ring-2 focus:outline-none"
        />
        <Search
            class="text-muted-foreground pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2"
            aria-hidden="true"
        />
    </form>
</template>
