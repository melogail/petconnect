<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch, nextTick } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import { MapPin } from 'lucide-vue-next';
import { useTranslations } from '@/composables/useTranslations';

interface Props {
    form: any;
    mapCenter: { lat: number; lng: number };
    mapMarker: { lat: number; lng: number };
    isLoadingLocation: boolean;
    isVisible?: boolean;
}

const props = defineProps<Props>();

const { t } = useTranslations();

const emit = defineEmits<{
    updateLocation: [lat: number, lng: number];
}>();

const isLoading = ref(false);
const mapContainer = ref<HTMLElement | null>(null);
let map: google.maps.Map | null = null;
let marker: google.maps.Marker | null = null;
let geocoder: google.maps.Geocoder | null = null;

// Google Maps API Key - should be set in .env as VITE_GOOGLE_MAPS_API_KEY
const API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';

const handleGetCurrentLocation = () => {
    if (navigator.geolocation) {
        isLoading.value = true;
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const { latitude, longitude } = position.coords;

                emit('updateLocation', latitude, longitude);

                if (map) {
                    const newPos = { lat: latitude, lng: longitude };
                    map.panTo(newPos);
                    map.setZoom(13);

                    if (marker) {
                        marker.setPosition(newPos);
                    } else {
                        marker = new google.maps.Marker({
                            position: newPos,
                            map: map,
                        });
                    }
                }
                isLoading.value = false;
            },
            (error) => {
                console.error('Error getting location', error);
                isLoading.value = false;
            },
        );
    } else {
        console.error('Geolocation is not supported by this browser.');
    }
};

onMounted(async () => {
    if (mapContainer.value) {
        try {
            // Load the Google Maps JavaScript API script
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${API_KEY}&callback=initMap`;
            script.async = true;
            script.defer = true;

            // Define the initMap callback
            (window as any).initMap = async () => {
                const { Map } = (await google.maps.importLibrary(
                    'maps',
                )) as google.maps.MapsLibrary;
                const { Marker } = (await google.maps.importLibrary(
                    'marker',
                )) as google.maps.MarkerLibrary;

                const mapOptions: google.maps.MapOptions = {
                    center: {
                        lat: props.mapCenter.lat || 39.8283,
                        lng: props.mapCenter.lng || -98.5795,
                    },
                    zoom: props.mapCenter.lat ? 13 : 4,
                    mapTypeControl: true,
                    streetViewControl: true,
                    fullscreenControl: true,
                    mapId: 'PET_LOCATION_MAP',
                };

                if (mapContainer.value) {
                    map = new Map(mapContainer.value, mapOptions);
                    geocoder = new google.maps.Geocoder();

                    // Add initial marker if coordinates exist
                    if (props.mapMarker.lat && props.mapMarker.lng) {
                        marker = new Marker({
                            position: {
                                lat: props.mapMarker.lat,
                                lng: props.mapMarker.lng,
                            },
                            map: map,
                        });
                    }

                    // Add click listener to map
                    map.addListener('click', (e: google.maps.MapMouseEvent) => {
                        if (e.latLng) {
                            const lat = e.latLng.lat();
                            const lng = e.latLng.lng();

                            // Update or create marker
                            if (marker) {
                                marker.position = e.latLng;
                            } else {
                                marker = new Marker({
                                    position: e.latLng,
                                    map: map,
                                });
                            }

                            emit('updateLocation', lat, lng);
                        }
                    });

                    // Invalidate size if already visible on mount
                    if (props.isVisible) {
                        nextTick(() => {
                            google.maps.event.trigger(map, 'resize');
                        });
                    }
                }
            };

            document.head.appendChild(script);
        } catch (error) {
            console.error('Error loading Google Maps:', error);
        }
    }
});

// Watch for visibility changes to fix rendering issues
watch(
    () => props.isVisible,
    (visible) => {
        if (visible && map) {
            nextTick(() => {
                google.maps.event.trigger(map, 'resize');
                if (props.mapCenter.lat && props.mapCenter.lng) {
                    map?.setCenter({
                        lat: props.mapCenter.lat,
                        lng: props.mapCenter.lng,
                    });
                }
            });
        }
    },
);

// Watch for center changes (e.g. from Geolocation)
watch(
    () => props.mapCenter,
    (newCenter) => {
        if (
            map &&
            newCenter &&
            typeof newCenter.lat === 'number' &&
            typeof newCenter.lng === 'number'
        ) {
            map.panTo({ lat: newCenter.lat, lng: newCenter.lng });
            map.setZoom(13);
        }
    },
    { deep: true },
);

// Watch for marker changes
watch(
    () => props.mapMarker,
    (newMarker) => {
        if (map) {
            const position = { lat: newMarker.lat, lng: newMarker.lng };
            if (marker) {
                marker.setPosition(position);
            } else if (
                newMarker &&
                typeof newMarker.lat === 'number' &&
                typeof newMarker.lng === 'number'
            ) {
                marker = new google.maps.Marker({
                    position: position,
                    map: map,
                });
            }
        }
    },
    { deep: true },
);

onUnmounted(() => {
    if (marker) {
        marker.setMap(null);
        marker = null;
    }
    if (map) {
        map = null;
    }
});
</script>

<template>
    <div id="step-2" class="step-container animate-fade-in">
        <Card
            class="group relative overflow-hidden border-2 border-blue-100/50 shadow-lg backdrop-blur-md transition-all duration-500 hover:border-blue-300 hover:shadow-2xl dark:border-blue-900/30 dark:bg-gray-800/70 dark:hover:border-blue-700"
        >
            <!-- Animated Background Gradient -->
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-br from-blue-50/30 via-cyan-50/20 to-sky-50/10 opacity-0 transition-opacity duration-700 group-hover:opacity-100 dark:from-blue-900/20 dark:via-cyan-900/10 dark:to-sky-900/5"
            ></div>
            <!-- Decorative Corner -->
            <div
                class="absolute end-0 top-0 h-32 w-32 rounded-bl-full bg-gradient-to-br from-blue-100/20 to-transparent opacity-50 dark:from-blue-900/10"
            ></div>
            <CardHeader class="relative z-10">
                <div class="flex items-center space-x-4">
                    <div
                        class="relative rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-600 p-3 text-white shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:shadow-xl"
                    >
                        <div
                            class="absolute inset-0 animate-pulse rounded-2xl bg-white/20"
                        ></div>
                        <MapPin class="relative z-10 h-6 w-6" />
                    </div>
                    <div>
                        <CardTitle
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                            >{{ t('wizard.location') }}</CardTitle
                        >
                        <CardDescription
                            class="text-gray-500 dark:text-gray-400"
                            >{{
                                t('wizard.where_is_your_pet_located')
                            }}</CardDescription
                        >
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Get Location Button -->
                <div class="flex justify-center">
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleGetCurrentLocation"
                        :disabled="isLoading"
                        class="group relative overflow-hidden border-2 border-primary-200 transition-all duration-300 hover:border-primary-400 dark:border-primary-800 dark:hover:border-primary-600"
                    >
                        <span class="relative z-10 flex items-center">
                            <MapPin
                                class="me-2 h-5 w-5 transition-transform group-hover:scale-110"
                                :class="{ 'animate-pulse': isLoading }"
                            />
                            {{
                                isLoading
                                    ? t('wizard.getting_location')
                                    : t('wizard.use_my_current_location')
                            }}
                        </span>
                        <span
                            class="absolute inset-0 bg-gradient-to-r from-primary-50 to-purple-50 opacity-0 transition-opacity duration-300 group-hover:opacity-100 dark:from-primary-900/20 dark:to-purple-900/20"
                        ></span>
                    </Button>
                </div>

                <!-- Google Map -->
                <div
                    class="relative z-0 h-64 overflow-hidden rounded-xl border-2 border-gray-200 shadow-lg dark:border-gray-700"
                >
                    <div ref="mapContainer" class="z-0 h-full w-full"></div>

                    <!-- Coordinates Display -->
                    <div
                        class="absolute bottom-3 end-3 start-3 z-[1000] rounded-lg bg-white/90 px-3 py-2 font-mono text-xs backdrop-blur-sm dark:bg-gray-800/90"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 dark:text-gray-400">{{
                                t('wizard.coordinates')
                            }}</span>
                            <span
                                class="font-semibold text-gray-800 dark:text-gray-200"
                            >
                                {{ mapMarker.lat.toFixed(6) }},
                                {{ mapMarker.lng.toFixed(6) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Address Display -->
                <div
                    v-if="form.location.address"
                    class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-900/20"
                >
                    <div class="flex items-start space-x-3">
                        <MapPin
                            class="mt-0.5 h-5 w-5 flex-shrink-0 text-primary-600 dark:text-primary-400"
                        />
                        <div class="flex-1">
                            <p
                                class="text-sm font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{ t('wizard.detected_address') }}
                            </p>
                            <p
                                class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                            >
                                {{ form.location.address }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Detailed Address Input -->
                <div class="space-y-2">
                    <Label
                        for="detailedAddress"
                        class="flex items-center space-x-2"
                    >
                        <span>{{ t('wizard.detailed_address') }}</span>
                        <span
                            class="text-xs text-gray-500 dark:text-gray-400"
                            >{{ t('wizard.optional') }}</span
                        >
                    </Label>
                    <Textarea
                        id="detailedAddress"
                        v-model="form.location.detailedAddress"
                        :placeholder="t('wizard.detailed_address_placeholder')"
                        class="min-h-[80px] resize-none"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ t('wizard.detailed_address_help') }}
                    </p>
                </div>

                <!-- Location Details Grid -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="city" is-required>{{
                            t('wizard.city')
                        }}</Label>
                        <Input
                            id="city"
                            v-model="form.location.city"
                            :placeholder="t('wizard.city_placeholder')"
                            required
                        />
                        <InputError :message="form.errors['location.city']" />
                    </div>
                    <div class="space-y-2">
                        <Label for="state" is-required>{{
                            t('wizard.state_province')
                        }}</Label>
                        <Input
                            id="state"
                            v-model="form.location.state"
                            :placeholder="
                                t('wizard.state_province_placeholder')
                            "
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="postalCode">{{
                            t('wizard.postal_code')
                        }}</Label>
                        <Input
                            id="postalCode"
                            v-model="form.location.postalCode"
                            :placeholder="t('wizard.postal_code_placeholder')"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="country" is-required>{{
                            t('wizard.country')
                        }}</Label>
                        <Input
                            id="country"
                            v-model="form.location.country"
                            :placeholder="t('wizard.country_placeholder')"
                            required
                        />
                        <InputError
                            :message="form.errors['location.country']"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
/* Fix text blurriness on hover/scale transforms */
.group:hover {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    transform-style: preserve-3d;
    -webkit-transform-style: preserve-3d;
}

/* Prevent text blur on transform elements */
[class*='transition'],
[class*='transform'],
[class*='scale'] {
    will-change: transform;
    transform: translateZ(0);
    -webkit-transform: translateZ(0);
}
</style>
