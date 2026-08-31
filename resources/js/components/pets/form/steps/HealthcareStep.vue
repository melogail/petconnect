<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/pets/form/FormField.vue';
import RepeaterShell from '@/components/pets/form/RepeaterShell.vue';
import TagInput from '@/components/pets/form/TagInput.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { petFormErrors, type PetFormState } from '@/lib/petForm';

/**
 * The clinical records and the vet's details.
 *
 * The three repeaters are **collection keys**: none of them carries `present`,
 * so an empty one is sent as `null` and never as `[]`. A repeater *row*, once
 * present, has to carry all of its leaves — `name`/`date` and `name`/`usage`
 * are each `present|nullable` — which is why adding a row adds both fields at
 * once, blank rather than absent.
 *
 * `medications`, `allergies`, `vetName` and `vetPhone` are owner-only on the
 * way back out: `PetDetailResource` omits them for everybody else.
 */
const { form } = defineProps<{ form: InertiaForm<PetFormState> }>();

const errors = computed(() => petFormErrors(form.errors));

const today = new Date().toISOString().slice(0, 10);

function addVaccination(): void {
    form.health.vaccinations = [
        ...form.health.vaccinations,
        { name: '', date: '' },
    ];
}

function removeVaccination(index: number): void {
    form.health.vaccinations = form.health.vaccinations.filter(
        (_, position) => position !== index,
    );
}

function addMedication(): void {
    form.health.medications = [
        ...form.health.medications,
        { name: '', usage: '' },
    ];
}

function removeMedication(index: number): void {
    form.health.medications = form.health.medications.filter(
        (_, position) => position !== index,
    );
}
</script>

<template>
    <div class="space-y-8">
        <section class="space-y-3">
            <div>
                <h3 class="font-medium">Vaccinations</h3>
                <p class="text-muted-foreground text-sm">
                    Public. A row with no name is dropped on save.
                </p>
            </div>

            <RepeaterShell
                :rows="form.health.vaccinations"
                add-label="Add a vaccination"
                empty-label="No vaccination records yet."
                @add="addVaccination"
                @remove="removeVaccination"
            >
                <template #row="{ index }">
                    <div class="grid gap-2">
                        <Label :for="`vaccination-name-${index}`">Name</Label>
                        <Input
                            :id="`vaccination-name-${index}`"
                            v-model="form.health.vaccinations[index].name"
                            maxlength="255"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`vaccination-date-${index}`">Date</Label>
                        <Input
                            :id="`vaccination-date-${index}`"
                            v-model="form.health.vaccinations[index].date"
                            type="date"
                            :max="today"
                        />
                    </div>
                </template>
            </RepeaterShell>
        </section>

        <Separator />

        <section class="space-y-3">
            <div>
                <h3 class="font-medium">Medications</h3>
                <p class="text-muted-foreground text-sm">
                    Only you ever see these.
                </p>
            </div>

            <RepeaterShell
                :rows="form.health.medications"
                add-label="Add a medication"
                empty-label="No medications recorded."
                @add="addMedication"
                @remove="removeMedication"
            >
                <template #row="{ index }">
                    <div class="grid gap-2">
                        <Label :for="`medication-name-${index}`">Name</Label>
                        <Input
                            :id="`medication-name-${index}`"
                            v-model="form.health.medications[index].name"
                            maxlength="255"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`medication-usage-${index}`">
                            How it is given
                        </Label>
                        <Input
                            :id="`medication-usage-${index}`"
                            v-model="form.health.medications[index].usage"
                            maxlength="255"
                        />
                    </div>
                </template>
            </RepeaterShell>
        </section>

        <Separator />

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField
                label="Allergies"
                field-id="pet-allergies"
                :error="errors['health.allergies']"
                hint="Only you ever see these."
                class="sm:col-span-2"
            >
                <TagInput
                    v-model="form.health.allergies"
                    input-id="pet-allergies"
                    placeholder="Chicken, pollen…"
                />
            </FormField>

            <FormField
                label="Veterinarian"
                field-id="pet-vet-name"
                :error="errors['health.vetName']"
                hint="Only you ever see this."
            >
                <Input
                    id="pet-vet-name"
                    v-model="form.health.vetName"
                    maxlength="255"
                />
            </FormField>

            <FormField
                label="Vet phone"
                field-id="pet-vet-phone"
                :error="errors['health.vetPhone']"
                hint="Only you ever see this."
            >
                <Input
                    id="pet-vet-phone"
                    v-model="form.health.vetPhone"
                    maxlength="20"
                />
            </FormField>
        </div>
    </div>
</template>
