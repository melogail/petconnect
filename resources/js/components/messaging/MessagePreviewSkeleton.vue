<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * The pulsing stand-in for the messages menu before its first response lands.
 *
 * Laid out to `MessagePreviewRow`'s geometry — 40px avatar, a name line, a
 * snippet line, the same `px-4 py-3` and the same divider — so the panel does
 * not jump when the rows arrive. Three rows, not five: five is what
 * `petconnect.messaging.preview_per_page` returns, but a skeleton taller than
 * the list it stands in for reads as a longer wait than it is.
 *
 * This is the sibling of `NotificationSkeleton`, which makes the same trade for
 * the same reason on the bell.
 */
</script>

<template>
    <div aria-hidden="true">
        <div
            v-for="row in 3"
            :key="row"
            class="border-border flex items-start gap-3 border-b px-4 py-3"
        >
            <Skeleton class="size-10 shrink-0 rounded-full" />
            <div class="min-w-0 flex-1 space-y-2">
                <Skeleton class="h-3.5 w-2/5" />
                <Skeleton class="h-3 w-4/5" />
            </div>
        </div>
    </div>
</template>
