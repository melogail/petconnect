<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { LocateFixed, MapPinOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import FormField from '@/components/pets/form/FormField.vue';
import LocationMap from '@/components/pets/LocationMap.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { fromCoordinateInput } from '@/lib/coordinates';
import { petFormErrors, type PetFormState } from '@/lib/petForm';
import { reverseGeocode } from '@/lib/reverseGeocode';

/**
 * Where the listing is.
 *
 * The map pin is **all or nothing**: `location.coordinates.lat` and `.lng`
 * carry `required_with` in both directions, so half a pair is a 422 — and an
 * unpinned listing has to send `null` rather than `{}`. `toPetPayload()` takes
 * care of both, and deliberately posts a half pair rather than swallowing it,
 * so the blank box gets the error instead of the pin quietly disappearing.
 * "Clear pin" empties both boxes at once, which is the other way out.
 *
 * The street address and the building detail are the two fields only the owner
 * ever sees back: `PetDetailResource` omits them entirely for anybody else.
 */
const { form } = defineProps<{ form: InertiaForm<PetFormState> }>();

const errors = computed(() => petFormErrors(form.errors));

const locating = ref(false);
const locateFailed = ref(false);

const lat = computed(() => fromCoordinateInput(form.location.lat));
const lng = computed(() => fromCoordinateInput(form.location.lng));

/**
 * There is something in either box — which is not the same question as
 * "there is a pin".
 *
 * `lat` and `lng` are `fromCoordinateInput()`, which answers `null` for a
 * string it cannot parse, so gating "Clear pin" on them hid the escape hatch in
 * exactly the state it exists for: type `abc` into Latitude and the only
 * control that empties the box disappears, leaving a half pair that 422s on
 * submit. The raw bound strings are what `clearPin()` writes and what the user
 * can see, so they are what the button follows.
 */
const hasPinInput = computed(
    () => form.location.lat !== '' || form.location.lng !== '',
);

function setPin(value: { lat: number; lng: number }): void {
    form.location.lat = String(value.lat);
    form.location.lng = String(value.lng);
}

function clearPin(): void {
    form.location.lat = '';
    form.location.lng = '';
}

async function useMyLocation(): Promise<void> {
    if (!navigator.geolocation) {
        locateFailed.value = true;

        return;
    }

    locating.value = true;
    locateFailed.value = false;

    navigator.geolocation.getCurrentPosition(
        async (position) => {
            setPin({
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            });

            const found = await reverseGeocode(
                position.coords.latitude,
                position.coords.longitude,
            );

            if (found) {
                form.location.address = found.address ?? form.location.address;
                form.location.city = found.city ?? form.location.city;
                form.location.state = found.state ?? form.location.state;
                form.location.postalCode =
                    found.postalCode ?? form.location.postalCode;
                form.location.country = found.country ?? form.location.country;
            }

            locating.value = false;
        },
        () => {
            locating.value = false;
            locateFailed.value = true;
        },
        { timeout: 10_000 },
    );
}
</script>

<template>
    <div class="space-y-5">
        <div class="space-y-3">
            <LocationMap
                :lat="lat"
                :lng="lng"
                interactive
                @update:coordinates="setPin"
            />

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="locating"
                    @click="useMyLocation"
                >
                    <Spinner v-if="locating" />
                    <LocateFixed v-else class="size-4" />
                    Use my location
                </Button>

                <Button
                    v-if="hasPinInput"
                    type="button"
                    variant="ghost"
                    @click="clearPin"
                >
                    <MapPinOff class="size-4" />
                    Clear pin
                </Button>
            </div>

            <p v-if="locateFailed" class="text-muted-foreground text-sm">
                We could not read your location. Fill the address in by hand.
            </p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField
                label="Latitude"
                field-id="pet-lat"
                :error="errors['location.coordinates.lat']"
                hint="A pin needs both boxes, or neither."
            >
                <Input
                    id="pet-lat"
                    v-model="form.location.lat"
                    inputmode="decimal"
                />
            </FormField>

            <FormField
                label="Longitude"
                field-id="pet-lng"
                :error="errors['location.coordinates.lng']"
            >
                <Input
                    id="pet-lng"
                    v-model="form.location.lng"
                    inputmode="decimal"
                />
            </FormField>

            <FormField
                label="City"
                field-id="pet-city"
                :error="errors['location.city']"
                required
            >
                <Input
                    id="pet-city"
                    v-model="form.location.city"
                    maxlength="255"
                    required
                />
            </FormField>

            <FormField
                label="State or province"
                field-id="pet-state"
                :error="errors['location.state']"
                required
            >
                <Input
                    id="pet-state"
                    v-model="form.location.state"
                    maxlength="255"
                    required
                />
            </FormField>

            <FormField
                label="Country"
                field-id="pet-country"
                :error="errors['location.country']"
                required
            >
                <Input
                    id="pet-country"
                    v-model="form.location.country"
                    maxlength="255"
                    required
                />
            </FormField>

            <FormField
                label="Postal code"
                field-id="pet-postal-code"
                :error="errors['location.postalCode']"
            >
                <Input
                    id="pet-postal-code"
                    v-model="form.location.postalCode"
                    maxlength="255"
                />
            </FormField>

            <FormField
                label="Street address"
                field-id="pet-address"
                :error="errors['location.address']"
                hint="Only you ever see this."
                class="sm:col-span-2"
            >
                <Input
                    id="pet-address"
                    v-model="form.location.address"
                    maxlength="255"
                />
            </FormField>

            <FormField
                label="Building detail"
                field-id="pet-detailed-address"
                :error="errors['location.detailedAddress']"
                hint="Apartment, floor, landmark. Only you ever see this."
                class="sm:col-span-2"
            >
                <Textarea
                    id="pet-detailed-address"
                    v-model="form.location.detailedAddress"
                    rows="3"
                    maxlength="1000"
                />
            </FormField>
        </div>
    </div>
</template>
