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
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ label(link.label) }}
                </Link>
            </Button>
            <Button
                v-else
                size="sm"
                :variant="link.active ? 'default' : 'ghost'"
                :aria-current="link.active ? 'page' : undefined"
                @click="emit('navigate', link.url)"
            >
                {{ label(link.label) }}
            </Button>
        </template>
    </nav>
</template>
