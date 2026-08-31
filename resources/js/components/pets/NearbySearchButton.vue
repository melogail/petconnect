<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LocateFixed, X } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import type { HomeFeedBounds } from '@/types';

/**
 * "Search near me".
 *
 * The feed becomes a nearby feed when the query string carries a coordinate
 * pair; `ListHomeFeedRequest` clamps the radius and refuses half a pair, and
 * `PetCardResource` then carries `distance` on every card.
 *
 * The coordinates are never props — the page is told only `nearby` and
 * `radius` — so switching them off writes nulls through `mergeQuery`, which
 * removes the three keys and leaves every filter beside them alone.
 */
const { nearby, radius, bounds } = defineProps<{
    nearby: boolean;
    radius: number | null;
    bounds: HomeFeedBounds;
}>();

const locating = ref(false);
const denied = ref(false);

function locate(): void {
    if (!navigator.geolocation) {
        denied.value = true;

        return;
    }

    locating.value = true;
    denied.value = false;

    navigator.geolocation.getCurrentPosition(
        (position) => {
            locating.value = false;

            router.get(
                home.url({
                    mergeQuery: {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        radius: radius ?? bounds.default_radius_km,
                        page: null,
                    },
                }),
                {},
                { preserveScroll: false },
            );
        },
        () => {
            locating.value = false;
            denied.value = true;
        },
        { timeout: 10_000 },
    );
}

function clear(): void {
    router.get(
        home.url({
            mergeQuery: {
                latitude: null,
                longitude: null,
                radius: null,
                page: null,
            },
        }),
        {},
        { preserveScroll: false },
    );
}
</script>

<template>
    <div class="flex flex-col items-start gap-1">
        <Button v-if="nearby" variant="secondary" @click="clear">
            <X class="size-4" />
            Within {{ radius }} km
        </Button>

        <Button v-else variant="outline" :disabled="locating" @click="locate">
            <Spinner v-if="locating" />
            <LocateFixed v-else class="size-4" />
            Near me
        </Button>

        <p v-if="denied" class="text-muted-foreground text-xs">
            We could not read your location. Check your browser permissions.
        </p>
    </div>
</template>
