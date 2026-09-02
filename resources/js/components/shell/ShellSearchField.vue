<script setup lang="ts">
import { Search } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The search box in the middle of the public header.
 *
 * **It is decorative and does nothing yet.** No `v-model`, no submit, no
 * request — exactly as in the legacy shell it was ported from, where the input
 * was wired to nothing either. `ListHomeFeedRequest` has no free-text rule
 * (its filters are coordinates, radius, taxonomy ids, age, listing type and
 * `vaccinated`), so there is no parameter to send. Wiring it needs a backend
 * change first; do not invent an endpoint here.
 *
 * It carries `disabled` rather than being a live input with no handler, which
 * is the one deviation from the legacy markup: a field that accepts a query
 * and silently drops it is worse than one that visibly is not ready, and
 * `disabled` also keeps it out of the tab order. It has to *look* unavailable
 * too, so it carries the same `disabled:cursor-not-allowed disabled:opacity-50`
 * pair as `ui/input` and `ui/textarea`, and the magnifier dims with it through
 * `peer-disabled:` — which is why the input comes first in the markup and the
 * absolutely positioned icon second. WCAG 1.4.3 exempts disabled controls from
 * the contrast minimum; `disabled` is what says the same thing to assistive
 * technology. Drop the two `disabled:` utilities and the `peer` pair when the
 * field is wired — the enabled colours underneath are already the live ones.
 */
const { t } = useTranslations();
</script>

<template>
    <div class="relative">
        <input
            type="search"
            disabled
            :placeholder="t('nav.search_placeholder')"
            :aria-label="t('nav.search_placeholder')"
            class="peer border-border bg-muted/50 text-foreground placeholder:text-muted-foreground focus:border-primary-400 focus:ring-primary-200 w-full rounded-full border py-2 ps-11 pe-4 text-sm focus:ring-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        />
        <Search
            class="text-muted-foreground pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 peer-disabled:opacity-50"
            aria-hidden="true"
        />
    </div>
</template>
