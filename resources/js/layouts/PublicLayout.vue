<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import PublicFooter from '@/components/shell/PublicFooter.vue';

/**
 * The shell for pages a guest may reach: the feed, a listing, a public
 * profile, the help centre and the support page.
 *
 * `AppLayout` is the signed-in shell and renders a sidebar that reads
 * `auth.user`, which is why `app.ts` mapped these to `null` and left a note
 * asking Phase 4 for a real public layout. This is it.
 *
 * The bottom padding on `<main>` is not decoration: `PublicFooter` is fixed
 * from `md` up, so it is out of flow there and would sit on top of the last
 * screenful of every page without it. Each value is the footer's measured
 * height at that breakpoint rounded up to the next spacing step:
 *
 * | width      | footer  | reserved      |
 * | ---------- | ------- | ------------- |
 * | 320–767    | in flow | none needed   |
 * | 768–1023   | 97px    | `md:pb-28` 112px |
 * | 1024+      | 73px    | `lg:pb-20` 80px  |
 *
 * Below `md` the footer is static, so it takes its own space and nothing is
 * reserved: it is 145px tall in English and 169px in Arabic at 320px wide,
 * which a fixed bar cannot carry under the sticky header at 400% zoom (WCAG
 * 1.4.10). Change the footer's padding or type scale and re-measure these.
 */
</script>

<template>
    <div class="bg-background flex min-h-screen flex-col">
        <PublicHeader />

        <main class="flex-grow md:pb-28 lg:pb-20">
            <slot />
        </main>

        <PublicFooter />
    </div>
</template>
