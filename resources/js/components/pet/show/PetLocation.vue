<script setup lang="ts">
import { MapPin, ExternalLink } from 'lucide-vue-next';

defineProps<{
    petDetails: {
        address?: string | null;
        detailed_address?: string | null;
        city?: string | null;
        state?: string | null;
        postal_code?: string | null;
        country?: string | null;
        latitude?: number | string | null;
        longitude?: number | string | null;
    };
}>();
</script>

<template>
    <div>
        <h4
            class="text-muted-foreground mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest"
        >
            <MapPin class="h-4 w-4 text-primary" />
            Location
        </h4>
        <div
            class="border-border/50 bg-muted/10 space-y-1 rounded-xl border p-4"
        >
            <p v-if="petDetails.address" class="text-foreground font-medium">
                {{ petDetails.address }}
            </p>
            <p
                v-if="petDetails.detailed_address"
                class="text-muted-foreground text-sm"
            >
                {{ petDetails.detailed_address }}
            </p>
            <p class="text-muted-foreground text-sm">
                <span v-if="petDetails.city">{{ petDetails.city }}</span>
                <span v-if="petDetails.city && petDetails.state">, </span>
                <span v-if="petDetails.state">{{ petDetails.state }}</span>
                <span
                    v-if="
                        petDetails.postal_code &&
                        (petDetails.city || petDetails.state)
                    "
                >
                    {{ petDetails.postal_code }}</span
                >
                <span
                    v-if="
                        petDetails.country &&
                        (petDetails.city ||
                            petDetails.state ||
                            petDetails.postal_code)
                    "
                    >,
                </span>
                <span v-if="petDetails.country">{{ petDetails.country }}</span>
            </p>
            <a
                v-if="petDetails.latitude && petDetails.longitude"
                :href="`https://www.google.com/maps?q=${petDetails.latitude},${petDetails.longitude}`"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
            >
                <ExternalLink class="h-4 w-4" />
                View on Google Maps
            </a>
        </div>
    </div>
</template>
