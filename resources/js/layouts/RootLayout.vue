<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';

/**
 * The outermost layout, nested above every page shell by `app.ts`.
 *
 * It exists so `<Toaster />` is mounted exactly once, for signed-in and
 * public pages alike. It previously lived in the starter kit's
 * `AppSidebarLayout` and `AppHeaderLayout` (both removed 2026-09-06 with the
 * rest of the sidebar shell) only, so the pages on `PublicLayout` —
 * `pets/Show` and `profile/Show` among them — had no toast host at all and
 * every `flash.toast` the server sent was dropped on the floor. Add a shell,
 * and it inherits the toaster; do not mount a second one.
 *
 * A page can still bypass all of it: `defineOptions({ layout })` set to a
 * component, a render function or an empty object replaces the whole stack
 * `app.ts` builds — no `RootLayout`, no toaster, no error anywhere. The safe
 * form, and the only one used here, is a plain props object such as
 * `{ breadcrumbs }` or `{ title, description }`, which is merged into the
 * layouts as props rather than replacing them.
 *
 * `display: contents` keeps this wrapper out of the box tree, so the shell it
 * wraps lays out exactly as it did when it was the root element.
 */
</script>

<template>
    <div class="contents">
        <slot />
        <Toaster />
    </div>
</template>
