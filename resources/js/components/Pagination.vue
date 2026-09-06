<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { PaginationLink } from '@/types';

/**
 * The page links of a Laravel paginator.
 *
 * **These come from `meta.links`, never from the top-level `links`.** A
 * paginated resource collection has both, and they are different shapes: the
 * top-level key is four navigation URLs and `meta.links` is the numbered
 * buttons this renders. Passing the wrong one throws on the first line of
 * `hasPages` — see the note on `Paginated` in `types/pagination.ts`.
 *
 * `only` scopes the visit to the prop being paged, which matters wherever one
 * page holds two paginators — `profile.show` carries `listings` and `reviews`
 * under separate page names, and turning a page of one must not reset the
 * other.
 *
 * ## `as="button"`, for a paginator whose endpoint is not a page
 *
 * `notifications.index` answers plain JSON and is fetched with `useHttp`, so
 * its `links[].url` is not somewhere a reader can be sent: following it as a
 * link would navigate the browser to a JSON document. In that mode each page
 * renders as a `<button>` and the url is emitted as `navigate` for the caller
 * to fetch. `only` is meaningless there and is ignored.
 *
 * The default stays `as="link"`: a real anchor is what the paged Inertia pages
 * want, and it is what makes a page of comments openable in a new tab.
 */
const {
    links,
    only = [],
    as = 'link',
} = defineProps<{
    links: PaginationLink[];
    only?: string[];
    /** `link` visits the url; `button` emits it and does nothing else. */
    as?: 'link' | 'button';
}>();

const emit = defineEmits<{ navigate: [url: string] }>();

const ENTITIES: Record<string, string> = {
    '&laquo;': '«',
    '&raquo;': '»',
    '&hellip;': '…',
    '&nbsp;': ' ',
};

function label(raw: string): string {
    return raw
        .replace(
            /&laquo;|&raquo;|&hellip;|&nbsp;/g,
            (match) => ENTITIES[match] ?? match,
        )
        .trim();
}

/**
 * A numbered page control's visible label is a bare number, and unnamed it
 * announces as one: a screen reader's link list read `1 2 3 4 5 6` with nothing
 * to say what the numbers are. The `aria-label="Pagination"` on the `<nav>`
 * names the region, not the controls inside it, and a control is reachable
 * without ever entering its region.
 *
 * Measured out of Chrome's accessibility tree (`Accessibility.getFullAXTree`
 * over CDP) against an isolated build on a throwaway sqlite database,
 * 2026-09-06. Both render modes were read, because they are different roles and
 * one is only reachable inside an open sheet: `/conversations` (`as="link"`)
 * computed `1` and `2` for its two page links; the notification sheet
 * (`as="button"`) computed `1` … `6`. After: `Page 1` … `Page 6` in both, with
 * `aria-current="page"` on the active one unchanged.
 *
 * Returns `undefined` for anything that is not a bare number — "Previous",
 * "Next »" and the "…" separator already say what they do, and an `aria-label`
 * would override their visible text rather than extend it, which is the
 * containment failure this is here to avoid. Vue emits no attribute at all for
 * `undefined`, so those controls render byte-identically.
 *
 * English, like this file's existing `aria-label="Pagination"`. The scheduled
 * i18n pass (.ai/rules/lang.md) owns `lang/*.json`; no key was added here.
 * Containment is locale-independent either way — the visible text is a digit
 * string and it is inside `Page 3` whatever word precedes it.
 */
function pageName(raw: string): string | undefined {
    const text = label(raw);

    return /^\d+$/.test(text) ? `Page ${text}` : undefined;
}

const hasPages = computed(
    () => links.filter((link) => link.url !== null).length > 1,
);
</script>

<template>
    <nav
        v-if="hasPages"
        class="flex flex-wrap items-center gap-1"
        aria-label="Pagination"
    >
        <template v-for="(link, index) in links" :key="index">
            <span
                v-if="link.url === null"
                class="text-muted-foreground px-3 py-1.5 text-sm"
            >
                {{ label(link.label) }}
            </span>
            <Button
                v-else-if="as === 'link'"
                as-child
                size="sm"
                :variant="link.active ? 'default' : 'ghost'"
            >
                <Link
                    :href="link.url"
                    :only="only"
                    preserve-scroll
                    preserve-state
                    :aria-label="pageName(link.label)"
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ label(link.label) }}
                </Link>
            </Button>
            <Button
                v-else
                size="sm"
                :variant="link.active ? 'default' : 'ghost'"
                :aria-label="pageName(link.label)"
                :aria-current="link.active ? 'page' : undefined"
                @click="emit('navigate', link.url)"
            >
                {{ label(link.label) }}
            </Button>
        </template>
    </nav>
</template>
