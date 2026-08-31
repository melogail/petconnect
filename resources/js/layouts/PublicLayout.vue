<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PublicHeader from '@/components/PublicHeader.vue';
import { help, support } from '@/routes';

/**
 * The shell for pages a guest may reach: the feed, a listing, a public
 * profile, the help centre and the support page.
 *
 * `AppLayout` is the signed-in shell and renders a sidebar that reads
 * `auth.user`, which is why `app.ts` mapped these to `null` and left a note
 * asking Phase 4 for a real public layout. This is it.
 *
 * This footer is the only route to `help` and `support` a guest has, and for
 * `support` it is very nearly the only one anybody has. No sidebar carries
 * either link — `AppSidebar` has neither — and the signed-in path to `help` is
 * one item in the account dropdown (`UserMenuContent`), which `PublicHeader`
 * also renders once there is a user. Nothing but this footer and the button at
 * the bottom of `Help` links `support` at all.
 */
</script>

<template>
    <div class="bg-background flex min-h-screen flex-col">
        <PublicHeader />

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-border border-t">
            <div
                class="text-muted-foreground mx-auto flex w-full max-w-7xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-6 text-sm sm:px-6"
            >
                <Link :href="help()" class="hover:text-foreground">
                    Help Center
                </Link>
                <Link :href="support()" class="hover:text-foreground">
                    Support
                </Link>
            </div>
        </footer>
    </div>
</template>
