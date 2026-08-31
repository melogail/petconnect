<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * The pulsing stand-in for a page of notifications that has not arrived yet.
 *
 * Three rows, laid out to the same geometry as `NotificationRow` — avatar,
 * two lines of text — so the panel does not jump when the real list replaces
 * it. Three because that is roughly a screenful in the sheet; the real page is
 * `petconnect.notifications.inbox_per_page`, and matching it exactly would mean
 * a skeleton taller than anything it ever stands in for.
 */
</script>

<template>
    <ul class="space-y-4" aria-hidden="true">
        <li v-for="row in 3" :key="row" class="flex items-start gap-3 p-2">
            <Skeleton class="size-9 shrink-0 rounded-full" />
            <div class="min-w-0 flex-1 space-y-2">
                <Skeleton class="h-3.5 w-4/5" />
                <Skeleton class="h-3 w-1/4" />
            </div>
        </li>
    </ul>
</template>
