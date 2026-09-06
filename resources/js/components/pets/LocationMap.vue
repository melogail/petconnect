<script setup lang="ts">
import { ExternalLink, MapPin } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { Spinner } from '@/components/ui/spinner';
import {
    externalMapUrl,
    loadMapLibraries,
    mapsAvailable,
} from '@/lib/googleMaps';

/**
 * A coordinate pair, on a map when one can be drawn.
 *
 * Two callers: the listing page shows the owner their own pin, and the form's
 * location step lets them move it. `interactive` is what separates them.
 *
 * When no `VITE_GOOGLE_MAPS_API_KEY` is set the component degrades to the
 * coordinates plus a link out to Google Maps, which is more use than the
 * legacy page's CSS-grid "map" and does not pretend to be one.
 *
 * `importLibrary` fetches the Maps SDK over the network, so there is a real
 * gap between mount and the first tile. The container is covered while that
 * runs, because an empty grey box is indistinguishable from a map that has
 * failed — and failing is the other thing that can happen here.
 */
const {
    lat = null,
    lng = null,
    interactive = false,
    zoom = 14,
} = defineProps<{
    lat?: number | null;
    lng?: number | null;
    interactive?: boolean;
    zoom?: number;
}>();

const emit = defineEmits<{
    'update:coordinates': [value: { lat: number; lng: number }];
}>();

const container = ref<HTMLDivElement | null>(null);
const map = shallowRef<google.maps.Map | null>(null);
const marker = shallowRef<google.maps.Marker | null>(null);
const failed = ref(false);
const loading = ref(true);

const hasPin = computed(() => lat !== null && lng !== null);

const canRenderMap = computed(() => mapsAvailable() && !failed.value);

const center = computed(() => ({ lat: lat ?? 0, lng: lng ?? 0 }));

async function initialize(): Promise<void> {
    if (!canRenderMap.value || container.value === null) {
        loading.value = false;

        return;
    }

    try {
        const { maps, marker: markerLibrary } = await loadMapLibraries();

        map.value = new maps.Map(container.value, {
            center: center.value,
            zoom: hasPin.value ? zoom : 2,
            disableDefaultUI: !interactive,
            clickableIcons: false,
        });

        marker.value = new markerLibrary.Marker({
            map: map.value,
            position: hasPin.value ? center.value : null,
            draggable: interactive,
        });

        if (!interactive) {
            return;
        }

        map.value.addListener('click', (event: google.maps.MapMouseEvent) => {
            if (event.latLng) {
                publish(event.latLng.lat(), event.latLng.lng());
            }
        });

        marker.value.addListener('dragend', () => {
            const position = marker.value?.getPosition();

            if (position) {
                publish(position.lat(), position.lng());
            }
        });
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

function publish(nextLat: number, nextLng: number): void {
    emit('update:coordinates', {
        lat: Number(nextLat.toFixed(8)),
        lng: Number(nextLng.toFixed(8)),
    });
}

/** Follow a pin that was moved by the geolocation button or the two inputs. */
watch(
    () => [lat, lng],
    () => {
        if (map.value === null || marker.value === null) {
            return;
        }

        if (!hasPin.value) {
            marker.value.setPosition(null);

            return;
        }

        marker.value.setPosition(center.value);
        map.value.setCenter(center.value);

        if ((map.value.getZoom() ?? 0) < zoom) {
            map.value.setZoom(zoom);
        }
    },
);

onMounted(initialize);

onBeforeUnmount(() => {
    marker.value?.setMap(null);
    marker.value = null;
    map.value = null;
});
</script>

<template>
    <div
        v-if="canRenderMap"
        class="bg-muted relative h-64 w-full overflow-hidden rounded-lg"
    >
        <div
            ref="container"
            class="size-full"
            role="application"
            aria-label="Listing location"
        />

        <div
            v-if="loading"
            class="bg-muted text-muted-foreground absolute inset-0 flex items-center justify-center gap-2 text-sm"
        >
            <Spinner status class="size-4" />
            Loading the map…
        </div>
    </div>

    <div
        v-else-if="hasPin"
        class="bg-muted/50 flex items-center justify-between gap-3 rounded-lg border border-dashed p-4"
    >
        <span class="flex items-center gap-2 text-sm">
            <MapPin class="text-muted-foreground size-4" />
            {{ lat }}, {{ lng }}
        </span>
        <a
            :href="externalMapUrl(lat as number, lng as number)"
            target="_blank"
            rel="noopener noreferrer"
            class="text-primary flex items-center gap-1 text-sm underline"
        >
            Open in Maps
            <ExternalLink class="size-3.5" />
        </a>
    </div>

    <p v-else class="text-muted-foreground text-sm">
        No map pin on this listing.
    </p>
</template>
