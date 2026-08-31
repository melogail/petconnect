<script setup lang="ts">
import { Stethoscope } from '@lucide/vue';
import { computed } from 'vue';
import DetailList from '@/components/DetailList.vue';
import type { DetailItem } from '@/components/DetailList.vue';
import PetPanel from '@/components/pets/PetPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useLocale } from '@/composables/useLocale';
import { formatDate } from '@/lib/datetime';
import type { PetDetail } from '@/types';

/**
 * The health group.
 *
 * `vaccinations` is public; `medications`, `allergies`, `vetName` and
 * `vetPhone` are **absent — not null — for a viewer who cannot update the
 * listing**, which is why every one of them is read with `??` rather than
 * rendered behind a single `is_owner` branch. The owner block is still gated on
 * `is_owner` so the heading does not appear with nothing under it.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const { tag } = useLocale();

/** `chronic_condition` -> `Chronic condition`. No labels ship with this page. */
const statusLabel = computed(() => {
    const words = pet.health.status.replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
});

const items = computed<DetailItem[]>(() => [
    { label: 'Health status', value: statusLabel.value },
    { label: 'Vaccinated', value: pet.health.vaccinated ? 'Yes' : 'No' },
    {
        label: 'Spayed / neutered',
        value: pet.health.spayedNeutered ? 'Yes' : 'No',
    },
    {
        label: 'Last vet visit',
        value: formatDate(pet.health.lastVetVisit, tag.value) || null,
    },
    { label: 'Special needs', value: pet.health.specialNeeds },
]);

const vaccinations = computed(() => pet.health.vaccinations ?? []);
const medications = computed(() => pet.health.medications ?? []);
const allergies = computed(() => pet.health.allergies ?? []);

const vet = computed<DetailItem[]>(() => [
    { label: 'Veterinarian', value: pet.health.vetName ?? null },
    { label: 'Vet phone', value: pet.health.vetPhone ?? null },
]);
</script>

<template>
    <PetPanel title="Health" :icon="Stethoscope">
        <div class="space-y-4">
            <DetailList :items="items" />

            <template v-if="vaccinations.length > 0">
                <Separator />
                <div class="space-y-2">
                    <h3 class="text-muted-foreground text-xs">Vaccinations</h3>
                    <ul class="space-y-1 text-sm">
                        <li
                            v-for="(record, index) in vaccinations"
                            :key="`${record.name}-${index}`"
                            class="flex flex-wrap items-baseline gap-2"
                        >
                            <span>{{ record.name }}</span>
                            <span class="text-muted-foreground text-xs">
                                {{ formatDate(record.date, tag) }}
                            </span>
                        </li>
                    </ul>
                </div>
            </template>

            <template v-if="pet.is_owner">
                <Separator />
                <div class="space-y-4">
                    <p class="text-muted-foreground text-xs">
                        Only you can see the rest of this panel.
                    </p>

                    <div v-if="medications.length > 0" class="space-y-2">
                        <h3 class="text-muted-foreground text-xs">
                            Medications
                        </h3>
                        <ul class="space-y-1 text-sm">
                            <li
                                v-for="(record, index) in medications"
                                :key="`${record.name}-${index}`"
                                class="flex flex-wrap items-baseline gap-2"
                            >
                                <span>{{ record.name }}</span>
                                <span class="text-muted-foreground text-xs">
                                    {{ record.usage }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="allergies.length > 0" class="space-y-2">
                        <h3 class="text-muted-foreground text-xs">Allergies</h3>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="allergy in allergies"
                                :key="allergy"
                                variant="outline"
                            >
                                {{ allergy }}
                            </Badge>
                        </div>
                    </div>

                    <DetailList :items="vet" />
                </div>
            </template>
        </div>
    </PetPanel>
</template>
