<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * One comment's worth of pulsing placeholder.
 *
 * The comments dialog opens with nothing in hand and fetches the thread as it
 * appears, so there is a beat with no content; three of these fill it. The
 * shape mirrors `CommentBody` — a 36px avatar beside a bubble — so nothing
 * jumps sideways when the real rows land.
 */
</script>

<template>
    <div class="flex gap-3">
        <Skeleton class="size-9 shrink-0 rounded-full" />
        <div class="min-w-0 flex-1 space-y-2">
            <Skeleton class="h-20 w-full rounded-2xl rounded-ss-sm" />
            <Skeleton class="ms-2 h-3 w-24" />
        </div>
    </div>
</template>
