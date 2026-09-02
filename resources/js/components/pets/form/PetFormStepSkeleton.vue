<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * What a wizard step looks like while its chunk is still in flight.
 *
 * The steps are loaded on demand, so the first visit to one has to render
 * *something* — an empty card reads as a broken step. The shape is a generic
 * label-and-field stack rather than any particular step's layout, because one
 * fallback serves all eight.
 *
 * Attributes do not inherit: Vue hands the async wrapper's props (`form`,
 * `options`, …) down as fallthrough attrs, and objects stringified onto a
 * `<div>` help nobody.
 */
defineOptions({ inheritAttrs: false });
</script>

<template>
    <div class="space-y-8" role="status" aria-label="Loading step">
        <div class="space-y-3">
            <Skeleton class="h-5 w-40" />
            <Skeleton class="h-4 w-72 max-w-full" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div v-for="field in 4" :key="field" class="space-y-2">
                <Skeleton class="h-4 w-24" />
                <Skeleton class="h-9 w-full" />
            </div>
        </div>

        <div class="space-y-2">
            <Skeleton class="h-4 w-28" />
            <Skeleton class="h-24 w-full" />
        </div>
    </div>
</template>
