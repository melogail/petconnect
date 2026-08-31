<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

/**
 * The bar across the top of the signed-in shell.
 *
 * It carries the notification bell because it is the one piece of chrome on
 * every page `AppSidebarLayout` wraps, and because the bell has to be somewhere
 * a reader can reach without first knowing they have something to read.
 *
 * The `auth.user` guard is not decoration. Every `notifications.*` route is
 * behind `auth` + `verified`, and `auth.user` is typed non-nullable while being
 * null for a guest (.ai/rules/types.md), so the check has to be made at runtime
 * or not at all. In practice this layout is only reached with a session, but a
 * bell that fires an unauthenticated fetch on mount would be a redirect to
 * login for its trouble.
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

        <NotificationBell v-if="user" />
    </header>
</template>
