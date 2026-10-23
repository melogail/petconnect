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
import { useTranslations } from '@/composables/useTranslations';

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

const { t } = useTranslations();

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
                <h3 class="font-medium">{{ t('pets.vaccinations') }}</h3>
                <p class="text-muted-foreground text-sm">
                    {{ t('wizard.vaccinations_desc') }}
                </p>
            </div>

            <RepeaterShell
                :rows="form.health.vaccinations"
                :add-label="t('wizard.add_vaccination')"
                :empty-label="t('wizard.no_vaccination_records')"
                @add="addVaccination"
                @remove="removeVaccination"
            >
                <template #row="{ index }">
                    <div class="grid gap-2">
                        <Label :for="`vaccination-name-${index}`">
                            {{ t('wizard.name') }}
                        </Label>
                        <Input
                            :id="`vaccination-name-${index}`"
                            v-model="form.health.vaccinations[index].name"
                            maxlength="255"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`vaccination-date-${index}`">
                            {{ t('wizard.date') }}
                        </Label>
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
                <h3 class="font-medium">{{ t('pets.medications') }}</h3>
                <p class="text-muted-foreground text-sm">
                    {{ t('wizard.only_you_see_these') }}
                </p>
            </div>

            <RepeaterShell
                :rows="form.health.medications"
                :add-label="t('wizard.add_medication')"
                :empty-label="t('wizard.no_medications')"
                @add="addMedication"
                @remove="removeMedication"
            >
                <template #row="{ index }">
                    <div class="grid gap-2">
                        <Label :for="`medication-name-${index}`">
                            {{ t('wizard.name') }}
                        </Label>
                        <Input
                            :id="`medication-name-${index}`"
                            v-model="form.health.medications[index].name"
                            maxlength="255"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`medication-usage-${index}`">
                            {{ t('wizard.how_given') }}
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
                :label="t('wizard.allergies')"
                field-id="pet-allergies"
                :error="errors['health.allergies']"
                :hint="t('wizard.only_you_see_these')"
                class="sm:col-span-2"
            >
                <TagInput
                    v-model="form.health.allergies"
                    input-id="pet-allergies"
                    :placeholder="t('wizard.allergies_placeholder')"
                />
            </FormField>

            <FormField
                :label="t('wizard.veterinarian')"
                field-id="pet-vet-name"
                :error="errors['health.vetName']"
                :hint="t('wizard.only_you_see_this')"
            >
                <Input
                    id="pet-vet-name"
                    v-model="form.health.vetName"
                    maxlength="255"
                />
            </FormField>

            <FormField
                :label="t('pets.vet_phone')"
                field-id="pet-vet-phone"
                :error="errors['health.vetPhone']"
                :hint="t('wizard.only_you_see_this')"
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
