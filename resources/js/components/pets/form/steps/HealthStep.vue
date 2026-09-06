<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/pets/form/FormField.vue';
import SelectInput from '@/components/pets/form/SelectInput.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { petFormErrors, type PetFormState } from '@/lib/petForm';
import type { PetFormOptions, PetHealthStatus } from '@/types';

/**
 * The health group's own fields. The three repeaters live in the healthcare
 * step, so this one stays about the listing rather than about its records.
 *
 * Every field here is `present|nullable`, and the group itself is
 * `present|array` and never null — which is expressible over multipart only
 * because these leaves are always on the wire. `specialNeeds` is free text, not
 * a flag: the backing column is a `text`.
 */
const { form } = defineProps<{
    form: InertiaForm<PetFormState>;
    options: PetFormOptions;
}>();

const errors = computed(() => petFormErrors(form.errors));

/** `before_or_equal:today` — the picker should not offer tomorrow either. */
const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <div class="grid gap-5 sm:grid-cols-2">
        <FormField
            label="Health status"
            field-id="pet-health-status"
            :error="errors['health.status']"
        >
            <SelectInput
                id="pet-health-status"
                :model-value="form.health.status"
                :options="options.healthStatuses"
                placeholder="Pick a health status"
                @update:model-value="
                    (value) =>
                        (form.health.status = value as PetHealthStatus | '')
                "
            />
        </FormField>

        <FormField
            label="Last vet visit"
            field-id="pet-last-vet-visit"
            :error="errors['health.lastVetVisit']"
        >
            <Input
                id="pet-last-vet-visit"
                v-model="form.health.lastVetVisit"
                type="date"
                :max="today"
            />
        </FormField>

        <div class="flex flex-col gap-3 sm:col-span-2">
            <div class="flex items-center gap-2">
                <Checkbox
                    id="pet-vaccinated"
                    :model-value="form.health.vaccinated"
                    @update:model-value="
                        (checked) => (form.health.vaccinated = checked === true)
                    "
                />
                <Label for="pet-vaccinated" class="cursor-pointer font-normal">
                    Vaccinated
                </Label>
            </div>

            <div class="flex items-center gap-2">
                <Checkbox
                    id="pet-spayed-neutered"
                    :model-value="form.health.spayedNeutered"
                    @update:model-value="
                        (checked) =>
                            (form.health.spayedNeutered = checked === true)
                    "
                />
                <Label
                    for="pet-spayed-neutered"
                    class="cursor-pointer font-normal"
                >
                    Spayed or neutered
                </Label>
            </div>
        </div>

        <FormField
            label="Special needs"
            field-id="pet-special-needs"
            :error="errors['health.specialNeeds']"
            hint="Anything a new owner would have to plan around."
            class="sm:col-span-2"
        >
            <Textarea
                id="pet-special-needs"
                v-model="form.health.specialNeeds"
                rows="4"
                maxlength="1000"
            />
        </FormField>
    </div>
</template>
