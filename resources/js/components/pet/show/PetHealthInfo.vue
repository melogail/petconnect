<script setup lang="ts">
import { Heart, Shield, AlertCircle } from 'lucide-vue-next';

defineProps<{
    petDetails: {
        health_status?: string | null;
        special_needs?: string | null;
        last_vet_visit?: string | null;
        vet_name?: string | null;
        vet_phone?: string | null;
        vaccinations: any[];
        medications: any[];
        allergies: any[];
    };
}>();

const formatDate = (dateStr: string) => {
    try {
        return new Date(dateStr).toLocaleDateString();
    } catch {
        return dateStr;
    }
};
</script>

<template>
    <div>
        <!-- Health & Veterinary Section -->
        <div
            v-if="
                petDetails.health_status ||
                petDetails.special_needs ||
                petDetails.last_vet_visit ||
                petDetails.vet_name ||
                petDetails.vet_phone
            "
            class="mb-6"
        >
            <h4
                class="text-muted-foreground mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest"
            >
                <Heart class="h-4 w-4 text-primary" />
                Health &amp; Veterinary
            </h4>
            <div
                class="divide-border/50 border-border/50 bg-muted/10 divide-y overflow-hidden rounded-xl border"
            >
                <div
                    v-if="petDetails.health_status"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <span class="text-muted-foreground text-sm"
                        >Health Status</span
                    >
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                        :class="{
                            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300':
                                petDetails.health_status === 'healthy',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300':
                                petDetails.health_status !== 'healthy',
                        }"
                    >
                        {{ petDetails.health_status }}
                    </span>
                </div>
                <div
                    v-if="petDetails.special_needs"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <span class="text-muted-foreground text-sm"
                        >Special Needs</span
                    >
                    <span class="text-sm font-medium">{{
                        petDetails.special_needs
                    }}</span>
                </div>
                <div
                    v-if="petDetails.last_vet_visit"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <span class="text-muted-foreground text-sm"
                        >Last Vet Visit</span
                    >
                    <span class="text-sm font-medium">{{
                        formatDate(petDetails.last_vet_visit)
                    }}</span>
                </div>
                <div
                    v-if="petDetails.vet_name"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <span class="text-muted-foreground text-sm">Vet Name</span>
                    <span class="text-sm font-medium">{{
                        petDetails.vet_name
                    }}</span>
                </div>
                <div
                    v-if="petDetails.vet_phone"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <span class="text-muted-foreground text-sm">Vet Phone</span>
                    <span class="text-sm font-medium">{{
                        petDetails.vet_phone
                    }}</span>
                </div>
            </div>
        </div>

        <!-- Healthcare Details -->
        <div
            v-if="
                petDetails.vaccinations?.length > 0 ||
                petDetails.medications?.length > 0 ||
                petDetails.allergies?.length > 0
            "
        >
            <h4
                class="text-muted-foreground mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest"
            >
                <Shield class="h-4 w-4 text-primary" />
                Healthcare Details
            </h4>
            <div class="space-y-4">
                <!-- Vaccinations -->
                <div v-if="petDetails.vaccinations?.length > 0">
                    <p class="text-muted-foreground mb-2 text-sm font-medium">
                        Vaccinations
                    </p>
                    <ul class="space-y-1.5">
                        <li
                            v-for="(vax, idx) in petDetails.vaccinations"
                            :key="idx"
                            class="flex items-center gap-2 text-sm"
                        >
                            <span
                                class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-green-500"
                            />
                            <span v-if="typeof vax === 'object'">
                                {{ vax.name }}
                                <span
                                    v-if="vax.date"
                                    class="text-muted-foreground ml-1"
                                    >({{ formatDate(vax.date) }})</span
                                >
                            </span>
                            <span v-else>{{ vax }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Medications -->
                <div v-if="petDetails.medications?.length > 0">
                    <p class="text-muted-foreground mb-2 text-sm font-medium">
                        Medications
                    </p>
                    <ul class="space-y-1.5">
                        <li
                            v-for="(med, idx) in petDetails.medications"
                            :key="idx"
                            class="flex items-center gap-2 text-sm"
                        >
                            <span
                                class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-blue-500"
                            />
                            <span v-if="typeof med === 'object'">
                                {{ med.name }}
                                <span
                                    v-if="med.usage"
                                    class="text-muted-foreground ml-1"
                                    >— {{ med.usage }}</span
                                >
                            </span>
                            <span v-else>{{ med }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Allergies -->
                <div v-if="petDetails.allergies?.length > 0">
                    <p
                        class="text-muted-foreground mb-2 flex items-center gap-1.5 text-sm font-medium"
                    >
                        <AlertCircle class="h-4 w-4 text-amber-500" />
                        Allergies
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="(allergy, idx) in petDetails.allergies"
                            :key="idx"
                            class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-400"
                        >
                            {{
                                typeof allergy === 'object'
                                    ? allergy.name || allergy
                                    : allergy
                            }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
