<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Plus, Trash2 } from 'lucide-vue-next';
import { useTranslations } from '@/composables/useTranslations';

interface Props {
    form?: any;
}

const props = defineProps<Props>();

const { t } = useTranslations();

const addVaccination = () => {
    if (!props.form?.health?.vaccinations) return;
    props.form.health.vaccinations.push({ date: '', name: '' });
};

const removeVaccination = (index: number) => {
    if (!props.form?.health?.vaccinations) return;
    props.form.health.vaccinations.splice(index, 1);
};

const addMedication = () => {
    if (!props.form?.health?.medications) return;
    props.form.health.medications.push({ name: '', usage: '' });
};

const removeMedication = (index: number) => {
    if (!props.form?.health?.medications) return;
    props.form.health.medications.splice(index, 1);
};

const addAllergy = () => {
    if (!props.form?.health?.allergies) return;
    props.form.health.allergies.push('');
};

const removeAllergy = (index: number) => {
    if (!props.form?.health?.allergies) return;
    props.form.health.allergies.splice(index, 1);
};
</script>

<template>
    <div id="step-7" class="step-container animate-fade-in">
        <Card
            class="group relative overflow-hidden border-2 border-rose-100/50 shadow-lg backdrop-blur-md transition-all duration-500 hover:border-rose-300 hover:shadow-2xl dark:border-rose-900/30 dark:bg-gray-800/70 dark:hover:border-rose-700"
        >
            <!-- Animated Background Gradient -->
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-br from-rose-50/30 via-pink-50/20 to-red-50/10 opacity-0 transition-opacity duration-700 group-hover:opacity-100 dark:from-rose-900/20 dark:via-pink-900/10 dark:to-red-900/5"
            ></div>
            <!-- Decorative Corner -->
            <div
                class="absolute end-0 top-0 h-32 w-32 rounded-bl-full bg-gradient-to-br from-rose-100/20 to-transparent opacity-50 dark:from-rose-900/10"
            ></div>
            <CardHeader class="relative z-10">
                <div class="flex items-center space-x-4">
                    <div
                        class="relative rounded-2xl bg-gradient-to-br from-rose-500 to-pink-600 p-3 text-white shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:shadow-xl"
                    >
                        <div
                            class="absolute inset-0 animate-pulse rounded-2xl bg-white/20"
                        ></div>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="relative z-10 h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
                            />
                        </svg>
                    </div>
                    <div>
                        <CardTitle
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                            >{{ t('wizard.healthcare_information') }}</CardTitle
                        >
                        <CardDescription
                            class="text-gray-500 dark:text-gray-400"
                            >{{
                                t('wizard.healthcare_information_desc')
                            }}</CardDescription
                        >
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Vaccination Records -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label>{{ t('wizard.vaccination_records') }}</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addVaccination"
                        >
                            <Plus class="me-2 h-4 w-4" />
                            {{ t('wizard.add_record') }}
                        </Button>
                    </div>
                    <div
                        v-if="
                            !form?.health?.vaccinations ||
                            form.health.vaccinations.length === 0
                        "
                        class="rounded-lg border-2 border-dashed border-gray-200 py-4 text-center text-sm italic text-gray-500"
                    >
                        {{ t('wizard.no_vaccination_records') }}
                    </div>
                    <div
                        v-for="(vax, index) in form.health.vaccinations"
                        :key="index"
                        class="animate-fade-in flex items-start gap-3"
                    >
                        <div class="flex-1 space-y-1">
                            <Label :for="`vax-date-${index}`" class="text-xs">{{
                                t('wizard.date')
                            }}</Label>
                            <Input
                                :id="`vax-date-${index}`"
                                type="date"
                                v-model="vax.date"
                            />
                        </div>
                        <div class="flex-[2] space-y-1">
                            <Label :for="`vax-name-${index}`" class="text-xs">{{
                                t('wizard.vaccine_name')
                            }}</Label>
                            <Input
                                :id="`vax-name-${index}`"
                                v-model="vax.name"
                                :placeholder="
                                    t('wizard.vaccine_name_placeholder')
                                "
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="mt-6 text-red-500 hover:bg-red-50 hover:text-red-700"
                            @click="removeVaccination(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Current Medications -->
                <div
                    class="space-y-3 border-t border-gray-100 pt-4 dark:border-gray-700"
                >
                    <div class="flex items-center justify-between">
                        <Label>{{ t('wizard.current_medications') }}</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addMedication"
                        >
                            <Plus class="me-2 h-4 w-4" />
                            {{ t('wizard.add_medication') }}
                        </Button>
                    </div>
                    <div
                        v-if="
                            !form?.health?.medications ||
                            form.health.medications.length === 0
                        "
                        class="rounded-lg border-2 border-dashed border-gray-200 py-4 text-center text-sm italic text-gray-500"
                    >
                        {{ t('wizard.no_medications') }}
                    </div>
                    <div
                        v-for="(med, index) in form.health.medications"
                        :key="index"
                        class="animate-fade-in flex items-start gap-3"
                    >
                        <div class="flex-1 space-y-1">
                            <Label :for="`med-name-${index}`" class="text-xs">{{
                                t('wizard.medication_name')
                            }}</Label>
                            <Input
                                :id="`med-name-${index}`"
                                v-model="med.name"
                                :placeholder="
                                    t('wizard.medication_name_placeholder')
                                "
                            />
                        </div>
                        <div class="flex-[2] space-y-1">
                            <Label
                                :for="`med-usage-${index}`"
                                class="text-xs"
                                >{{ t('wizard.usage_purpose') }}</Label
                            >
                            <Input
                                :id="`med-usage-${index}`"
                                v-model="med.usage"
                                :placeholder="
                                    t('wizard.usage_purpose_placeholder')
                                "
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="mt-6 text-red-500 hover:bg-red-50 hover:text-red-700"
                            @click="removeMedication(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Allergies -->
                <div
                    class="space-y-3 border-t border-gray-100 pt-4 dark:border-gray-700"
                >
                    <div class="flex items-center justify-between">
                        <Label>{{ t('wizard.allergies') }}</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addAllergy"
                        >
                            <Plus class="me-2 h-4 w-4" />
                            {{ t('wizard.add_allergy') }}
                        </Button>
                    </div>
                    <div
                        v-if="
                            !form?.health?.allergies ||
                            form.health.allergies.length === 0
                        "
                        class="rounded-lg border-2 border-dashed border-gray-200 py-4 text-center text-sm italic text-gray-500"
                    >
                        {{ t('wizard.no_allergies') }}
                    </div>
                    <div
                        v-for="(allergy, index) in form.health.allergies"
                        :key="index"
                        class="animate-fade-in flex items-center gap-3"
                    >
                        <div class="flex-1">
                            <Input
                                v-model="form.health.allergies[index]"
                                :placeholder="t('wizard.allergies_placeholder')"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="text-red-500 hover:bg-red-50 hover:text-red-700"
                            @click="removeAllergy(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Veterinarian Information -->
                <div
                    class="space-y-2 border-t border-gray-100 pt-4 dark:border-gray-700"
                >
                    <Label>{{ t('wizard.veterinarian_information') }}</Label>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Input
                                id="vet-name"
                                v-model="form.health.vetName"
                                :placeholder="
                                    t('wizard.veterinarian_name_placeholder')
                                "
                            />
                        </div>
                        <div>
                            <Input
                                id="vet-phone"
                                v-model="form.health.vetPhone"
                                :placeholder="
                                    t('wizard.phone_number_placeholder')
                                "
                                type="tel"
                            />
                        </div>
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
