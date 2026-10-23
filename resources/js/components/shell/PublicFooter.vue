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
 * `nav.contacts` beside them goes to `support` too: the contact form is the
 * only contact channel and it lives on that page (`ContactDialog`). The other
 * four legacy footer entries (`privacy`, `terms`, `cookies`, `report_issue`)
 * were `href="#"` anchors in the legacy app, then plain text here; there is
 * still no route behind any of them, so they are gone rather than rendered as
 * labels that look like links and do nothing. Restore one when its page exists.
 *
 * Every entry is a real link, so they all share one style: `text-foreground`
 * (15.6:1 light), `font-semibold`, underline on hover. The copyright line is
 * the only `text-muted-foreground` here; that token is #746986 on #F6F4FB,
 * 4.68:1 (its previous 48% lightness, #766C89, measured 4.49:1 in the browser
 * — under AA, which is why it moved).
 */
const { t } = useTranslations();

const year = new Date().getFullYear();
</script>

<template>
    <footer
        class="border-border bg-background/90 border-t backdrop-blur-md md:fixed md:inset-x-0 md:bottom-0 md:z-40"
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
                    class="text-foreground hover:text-primary text-xs font-semibold underline-offset-4 transition-colors duration-200 ease-out hover:underline"
                >
                    {{ t('nav.help_center') }}
                </Link>
                <Link
                    :href="support()"
                    class="text-foreground hover:text-primary text-xs font-semibold underline-offset-4 transition-colors duration-200 ease-out hover:underline"
                >
                    {{ t('support.title') }}
                </Link>
                <Link
                    :href="support()"
                    class="text-foreground hover:text-primary text-xs font-semibold underline-offset-4 transition-colors duration-200 ease-out hover:underline"
                >
                    {{ t('nav.contacts') }}
                </Link>
            </div>
        </div>
    </footer>
</template>
