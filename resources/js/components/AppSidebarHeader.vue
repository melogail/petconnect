<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import MessagesDropdown from '@/components/messaging/MessagesDropdown.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

/**
 * The bar across the top of the signed-in shell.
 *
 * It carries the notification bell and the messages menu because it is the one
 * piece of chrome on every page `AppSidebarLayout` wraps, and because both have
 * to be somewhere a reader can reach without first knowing they have something
 * to read. They are in legacy's order — bell, then messages — which is the
 * order `PublicHeader` puts them in too, so the pair does not swap sides as a
 * reader moves between the two shells.
 *
 * `AppHeader` is the third header in this repo and is **orphaned**: only
 * `layouts/app/AppHeaderLayout.vue` imports it, and nothing imports that
 * (`RootLayout` records the same). It is deliberately left out of the phase 5
 * sweep rather than given a messages menu nobody would see.
 *
 * The `v-if="user"` around the pair is **layout grouping**, matching the
 * signed-in cluster `PublicHeader` builds, and not the thing that keeps a
 * guest from fetching. Both controls enforce that themselves: `canRead` is
 * `auth.user?.email_verified_at != null` in each of them — false for a null
 * viewer, so guests and unverified accounts fall out of the same predicate —
 * and each gates its render *and* its `onMounted` fetch on it
 * (`NotificationBell.vue:125` and `:133-137`, `MessagesDropdown.vue:131` and
 * `:139-143`). This layout is only reached with a session anyway, but even
 * without the `v-if` neither control would render or request anything for a
 * reader the `auth` + `verified` routes would turn away.
 */
withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();

const user = computed(() => page.props.auth.user ?? null);
</script>

<template>
    <header
        class="border-sidebar-border/70 flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <template v-if="user">
            <NotificationBell />

            <MessagesDropdown />
        </template>
    </header>
</template>
