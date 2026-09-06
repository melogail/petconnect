<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import FooterSocialMarks from '@/components/shell/FooterSocialMarks.vue';
import { useTranslations } from '@/composables/useTranslations';
import { help, support } from '@/routes';

/**
 * The bar at the bottom of every public page: copyright, social marks, links.
 *
 * ## Fixed from `md` up only, and `PublicLayout` pads for it there
 *
 * `fixed inset-x-0 bottom-0` matches the legacy shell and is the design intent,
 * but only where it fits. Below `md` this bar stacks into three rows — 145px in
 * English, 169px in Arabic at 320px wide — and a bar that size pinned under a
 * sticky header leaves almost nothing to read at 400% zoom (WCAG 1.4.10). So it
 * stays in flow below `md` and costs nothing on a scrolling feed. The bottom
 * padding on `<main>` in `PublicLayout` clears it at the breakpoints where it
 * is fixed — change the height here and re-measure those values.
 *
 * ## `help` and `support` are load bearing, the legal links are not
 *
 * This is the **only** route a guest has to `help` or `support`: there is no
 * sidebar any more (and the removed `AppSidebar` carried neither), and the
 * signed-in path to `help` is one item in the account dropdown. Nothing but this footer and the button
 * at the bottom of `Help` links `support` at all. They came from the target's
 * own footer, not the legacy one, and they stay first in the row.
 *
 * The five legal entries beside them (`privacy`, `terms`, `cookies`,
 * `contacts`, `report_issue`) are placeholders. They were `href="#"` anchors
 * in the legacy app and there is still no route behind any of them, so they
 * render as plain text rather than as anchors that go nowhere — a link that
 * does not navigate is worse than a label. Turn each into a `<Link>` when its
 * route exists.
 *
 * What tells the two apart on screen is weight, not colour: the two real links
 * are `font-semibold` and underline on hover, the labels are `font-normal` with
 * no hover state at all. They previously carried `/70`, which read as 2.82:1 in
 * light mode — an AA failure that also happened to be the only thing
 * distinguishing them. Never mark a non-interactive element by dimming it.
 */
const { t } = useTranslations();

const legalKeys = [
    'nav.privacy',
    'nav.terms',
    'nav.cookies',
    'nav.contacts',
    'nav.report_issue',
];

const year = new Date().getFullYear();
</script>

<template>
    <footer
        class="border-border bg-background/80 border-t backdrop-blur-md md:fixed md:inset-x-0 md:bottom-0 md:z-40"
    >
        <div
            class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-4 px-4 py-4 sm:px-6 md:grid-cols-3"
        >
            <p class="text-muted-foreground text-center text-sm md:text-start">
                {{ t('nav.footer_copyright', { year }) }}
            </p>

            <FooterSocialMarks />

            <div
                class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 md:justify-end"
            >
                <Link
                    :href="help()"
                    class="text-muted-foreground hover:text-primary-600 dark:hover:text-primary-400 text-xs font-semibold underline-offset-4 transition-colors hover:underline"
                >
                    {{ t('nav.help_center') }}
                </Link>
                <Link
                    :href="support()"
                    class="text-muted-foreground hover:text-primary-600 dark:hover:text-primary-400 text-xs font-semibold underline-offset-4 transition-colors hover:underline"
                >
                    {{ t('support.title') }}
                </Link>
                <span
                    v-for="key in legalKeys"
                    :key="key"
                    class="text-muted-foreground text-xs font-normal"
                >
                    {{ t(key) }}
                </span>
            </div>
        </div>
    </footer>
</template>
