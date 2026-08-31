<script setup lang="ts">
import { Info } from '@lucide/vue';
import { computed } from 'vue';
import DetailList from '@/components/DetailList.vue';
import type { DetailItem } from '@/components/DetailList.vue';
import PetPanel from '@/components/pets/PetPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import type { PetDetail } from '@/types';

/**
 * The description, the personality traits and the owner's free-form extras.
 *
 * `additionalInfo` is a **keyed map**, so it is rendered as whatever labels the
 * owner typed. The legacy page instead string-matched the keys against
 * hardcoded English labels ("Good with Kids", "Size") to pull three of them
 * into fixed slots, which showed nothing at all for a listing written in
 * Arabic.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const traits = computed(() => pet.traits ?? []);

const extras = computed<DetailItem[]>(() =>
    Object.entries(pet.additionalInfo ?? {}).map(([label, value]) => ({
        label,
        value,
    })),
);

const hasContent = computed(
    () =>
        pet.description.trim() !== '' ||
        traits.value.length > 0 ||
        extras.value.length > 0,
);
</script>

<template>
    <PetPanel v-if="hasContent" title="About" :icon="Info">
        <div class="space-y-4">
            <p
                v-if="pet.description"
                class="text-sm leading-relaxed whitespace-pre-line"
            >
                {{ pet.description }}
            </p>

            <template v-if="traits.length > 0">
                <Separator />
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="trait in traits"
                        :key="trait"
                        variant="secondary"
                    >
                        {{ trait }}
                    </Badge>
                </div>
            </template>

            <template v-if="extras.length > 0">
                <Separator />
                <DetailList :items="extras" />
            </template>
        </div>
    </PetPanel>
</template>
