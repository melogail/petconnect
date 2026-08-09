<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MapPin, Crosshair } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { Loader } from '@googlemaps/js-api-loader';

const { t } = useTranslations();

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
});

const mapContainer = ref<HTMLElement | null>(null);
const map = ref<google.maps.Map | null>(null);
const marker = ref<google.maps.Marker | null>(null);
const isLoadingLocation = ref(false);
const mapError = ref<string | null>(null);

const initMap = async () => {
    const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

    if (!apiKey) {
        mapError.value = t('profile.maps_unavailable');
        return;
    }

    const loader = new Loader({
        apiKey: apiKey,
        version: 'weekly',
        libraries: ['maps', 'marker'],
    });

    try {
        const { Map } = (await loader.importLibrary(
            'maps',
        )) as google.maps.MapsLibrary;
        const { Marker } = (await loader.importLibrary(
            'marker',
        )) as google.maps.MarkerLibrary;

        const defaultLocation = { lat: 40.7128, lng: -74.006 }; // New York fallback
        // Parse float only if value exists, default to defaultLocation
        const lat = props.form.lat
            ? parseFloat(props.form.lat)
            : defaultLocation.lat;
        const lng = props.form.lng
            ? parseFloat(props.form.lng)
            : defaultLocation.lng;

        const userLocation = { lat, lng };

        map.value = new Map(mapContainer.value as HTMLElement, {
            center: userLocation,
            zoom: 13,
            // mapId removed to avoid requirement for specific map configuration in Google Console
            disableDefaultUI: false,
            clickableIcons: false,
        });

        marker.value = new Marker({
            position: userLocation,
            map: map.value,
            title: t('profile.your_location'),
            draggable: true,
            animation: google.maps.Animation.DROP,
        });

        // Update form on drag end
        marker.value.addListener(
            'dragend',
            (event: google.maps.MapMouseEvent) => {
                if (event.latLng) {
                    updateLocation(event.latLng.lat(), event.latLng.lng());
                }
            },
        );

        // Update marker on map click
        map.value.addListener('click', (event: google.maps.MapMouseEvent) => {
            if (event.latLng) {
                marker.value?.setPosition(event.latLng);
                updateLocation(event.latLng.lat(), event.latLng.lng());
            }
        });
    } catch (error) {
        console.error('Error loading Google Maps:', error);
        mapError.value = t('profile.maps_failed');
    }
};

const updateLocation = (lat: number, lng: number) => {
    props.form.lat = lat;
    props.form.lng = lng;
    // Optional: Reverse geocode here to get address/timezone
};

const getCurrentLocation = () => {
    if (navigator.geolocation) {
        isLoadingLocation.value = true;
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                updateLocation(lat, lng);

                if (map.value && marker.value) {
                    const newPos = { lat, lng };
                    map.value.setCenter(newPos);
                    marker.value.setPosition(newPos);
                    map.value.setZoom(15);
                }
                isLoadingLocation.value = false;
            },
            (error) => {
                console.error('Error getting location:', error);
                isLoadingLocation.value = false;
                // Handle error (permission denied, time out, etc.)
                alert(t('profile.location_services_required'));
            },
            { enableHighAccuracy: true },
        );
    } else {
        alert(t('profile.geolocation_unsupported'));
    }
};

onMounted(() => {
    initMap();
});

// Watch for form updates (e.g. if loaded asynchronously) to update map center
watch(
    () => [props.form.lat, props.form.lng],
    ([newLat, newLng]) => {
        if (map.value && marker.value && newLat !== null && newLng !== null) {
            const pos = {
                lat: parseFloat(newLat as string),
                lng: parseFloat(newLng as string),
            };
            if (
                Math.abs(marker.value.getPosition()?.lat()! - pos.lat) >
                    0.0001 ||
                Math.abs(marker.value.getPosition()?.lng()! - pos.lng) > 0.0001
            ) {
                marker.value.setPosition(pos);
                map.value.setCenter(pos);
            }
        }
    },
);
</script>

<template>
    <div class="animate-in fade-in slide-in-from-end-4 space-y-6 duration-300">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ t('profile.location_details') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('profile.location_details_desc') }}
                </p>
            </div>
            <Button
                variant="outline"
                size="sm"
                @click.prevent="getCurrentLocation"
                :disabled="isLoadingLocation"
                class="flex items-center gap-2"
            >
                <Crosshair
                    class="h-4 w-4"
                    :class="{ 'animate-spin': isLoadingLocation }"
                />
                {{
                    isLoadingLocation
                        ? t('wizard.getting_location')
                        : t('wizard.use_my_current_location')
                }}
            </Button>
        </div>
        <Separator />

        <!-- Google Map Container -->
        <div
            class="h-64 w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
        >
            <div
                v-if="mapError"
                class="flex h-full w-full items-center justify-center bg-red-50 p-4 text-center text-sm text-red-500 dark:bg-red-900/10"
            >
                {{ mapError }}
            </div>
            <div
                v-else
                ref="mapContainer"
                class="flex h-full w-full items-center justify-center bg-gray-100 text-gray-400 dark:bg-gray-800"
            >
                {{ t('profile.loading_map') }}
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="col-span-2 space-y-2">
                <Label
                    for="address"
                    :class="{ 'text-red-500': form.errors.address }"
                    >{{ t('profile.street_address') }}</Label
                >
                <div class="relative">
                    <MapPin
                        class="absolute start-2.5 top-3 h-4 w-4 text-gray-500 dark:text-gray-400"
                    />
                    <Input
                        id="address"
                        v-model="form.address"
                        class="bg-gray-50/50 ps-9 dark:bg-gray-900/20"
                        :placeholder="t('profile.street_address_placeholder')"
                        :class="{ 'border-red-500': form.errors.address }"
                    />
                    <p v-if="form.errors.address" class="text-red-500">
                        {{ form.errors.address }}
                    </p>
                </div>
            </div>
            <div class="space-y-2">
                <Label
                    for="city"
                    :class="{ 'text-red-500': form.errors.city }"
                    >{{ t('profile.city') }}</Label
                >
                <Input
                    id="city"
                    v-model="form.city"
                    class="bg-gray-50/50 dark:bg-gray-900/20"
                    :class="{ 'border-red-500': form.errors.city }"
                />
                <p v-if="form.errors.city" class="text-red-500">
                    {{ form.errors.city }}
                </p>
            </div>
            <div class="space-y-2">
                <Label
                    for="state"
                    :class="{ 'text-red-500': form.errors.state }"
                    >{{ t('profile.state_province') }}</Label
                >
                <Input
                    id="state"
                    v-model="form.state"
                    class="bg-gray-50/50 dark:bg-gray-900/20"
                    :class="{ 'border-red-500': form.errors.state }"
                />
                <p v-if="form.errors.state" class="text-red-500">
                    {{ form.errors.state }}
                </p>
            </div>
            <div class="space-y-2">
                <Label
                    for="country"
                    :class="{ 'text-red-500': form.errors.country }"
                    >{{ t('profile.country') }}</Label
                >
                <Select
                    v-model="form.country"
                    :class="{ 'border-red-500': form.errors.country }"
                >
                    <SelectTrigger class="bg-gray-50/50 dark:bg-gray-900/20">
                        <SelectValue
                            :placeholder="t('profile.select_country')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="us">{{
                            t('profile.united_states')
                        }}</SelectItem>
                        <SelectItem value="ca">{{
                            t('profile.canada')
                        }}</SelectItem>
                        <SelectItem value="uk">{{
                            t('profile.united_kingdom')
                        }}</SelectItem>
                        <SelectItem value="au">{{
                            t('profile.australia')
                        }}</SelectItem>
                        <!-- Add more countries as needed -->
                    </SelectContent>
                </Select>
                <p v-if="form.errors.country" class="text-red-500">
                    {{ form.errors.country }}
                </p>
            </div>

            <div class="space-y-2">
                <Label
                    for="timezone"
                    :class="{ 'text-red-500': form.errors.timezone }"
                    >{{ t('profile.timezone') }}</Label
                >
                <Input
                    id="timezone"
                    v-model="form.timezone"
                    class="bg-gray-50/50 dark:bg-gray-900/20"
                    :class="{ 'border-red-500': form.errors.timezone }"
                    :placeholder="t('profile.timezone_placeholder')"
                />
                <p v-if="form.errors.timezone" class="text-red-500">
                    {{ form.errors.timezone }}
                </p>
            </div>

            <div class="col-span-2 grid grid-cols-2 gap-6">
                <div class="space-y-2">
                    <Label
                        for="lat"
                        :class="{ 'text-red-500': form.errors.lat }"
                        >{{ t('profile.latitude') }}</Label
                    >
                    <Input
                        id="lat"
                        v-model="form.lat"
                        class="bg-gray-50/50 dark:bg-gray-900/20"
                        readonly
                    />
                </div>
                <div class="space-y-2">
                    <Label
                        for="lng"
                        :class="{ 'text-red-500': form.errors.lng }"
                        >{{ t('profile.longitude') }}</Label
                    >
                    <Input
                        id="lng"
                        v-model="form.lng"
                        class="bg-gray-50/50 dark:bg-gray-900/20"
                        readonly
                    />
                </div>
            </div>
        </div>
    </div>
</template>
