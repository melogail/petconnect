<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Plus, Trash2 } from 'lucide-vue-next';

interface Props {
    form?: any;
}

const props = defineProps<Props>();

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
        <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-rose-100/50 dark:border-rose-900/30 hover:border-rose-300 dark:hover:border-rose-700 shadow-lg">
            <!-- Animated Background Gradient -->
            <div class="absolute -z-10 inset-0 bg-gradient-to-br from-rose-50/30 via-pink-50/20 to-red-50/10 dark:from-rose-900/20 dark:via-pink-900/10 dark:to-red-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
            <!-- Decorative Corner -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-rose-100/20 to-transparent dark:from-rose-900/10 rounded-bl-full opacity-50"></div>
            <CardHeader class="relative z-10">
                <div class="flex items-center space-x-4">
                    <div class="relative p-3 rounded-2xl bg-gradient-to-br from-rose-500 to-pink-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                        <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <div>
                        <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Healthcare Information</CardTitle>
                        <CardDescription class="text-gray-500 dark:text-gray-400">Your pet's medical history and care</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Vaccination Records -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label>Vaccination Records</Label>
                        <Button type="button" variant="outline" size="sm" @click="addVaccination">
                            <Plus class="w-4 h-4 mr-2" /> Add Record
                        </Button>
                    </div>
                    <div v-if="!form?.health?.vaccinations || form.health.vaccinations.length === 0" class="text-sm text-gray-500 italic text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">
                        No vaccination records added
                    </div>
                    <div v-for="(vax, index) in form.health.vaccinations" :key="index" class="flex gap-3 items-start animate-fade-in">
                        <div class="flex-1 space-y-1">
                            <Label :for="`vax-date-${index}`" class="text-xs">Date</Label>
                            <Input :id="`vax-date-${index}`" type="date" v-model="vax.date" />
                        </div>
                        <div class="flex-[2] space-y-1">
                            <Label :for="`vax-name-${index}`" class="text-xs">Vaccine Name</Label>
                            <Input :id="`vax-name-${index}`" v-model="vax.name" placeholder="e.g. Rabies" />
                        </div>
                        <Button type="button" variant="ghost" size="icon" class="mt-6 text-red-500 hover:text-red-700 hover:bg-red-50" @click="removeVaccination(index)">
                            <Trash2 class="w-4 h-4" />
                        </Button>
                    </div>
                </div>

                <!-- Current Medications -->
                <div class="space-y-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <Label>Current Medications</Label>
                        <Button type="button" variant="outline" size="sm" @click="addMedication">
                            <Plus class="w-4 h-4 mr-2" /> Add Medication
                        </Button>
                    </div>
                    <div v-if="!form?.health?.medications || form.health.medications.length === 0" class="text-sm text-gray-500 italic text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">
                        No medications added
                    </div>
                    <div v-for="(med, index) in form.health.medications" :key="index" class="flex gap-3 items-start animate-fade-in">
                        <div class="flex-1 space-y-1">
                            <Label :for="`med-name-${index}`" class="text-xs">Medication Name</Label>
                            <Input :id="`med-name-${index}`" v-model="med.name" placeholder="e.g. Heartgard" />
                        </div>
                        <div class="flex-[2] space-y-1">
                            <Label :for="`med-usage-${index}`" class="text-xs">Usage/Purpose</Label>
                            <Input :id="`med-usage-${index}`" v-model="med.usage" placeholder="e.g. Heartworm prevention" />
                        </div>
                        <Button type="button" variant="ghost" size="icon" class="mt-6 text-red-500 hover:text-red-700 hover:bg-red-50" @click="removeMedication(index)">
                            <Trash2 class="w-4 h-4" />
                        </Button>
                    </div>
                </div>

                <!-- Allergies -->
                <div class="space-y-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <Label>Allergies</Label>
                        <Button type="button" variant="outline" size="sm" @click="addAllergy">
                            <Plus class="w-4 h-4 mr-2" /> Add Allergy
                        </Button>
                    </div>
                    <div v-if="!form?.health?.allergies || form.health.allergies.length === 0" class="text-sm text-gray-500 italic text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">
                        No allergies listed
                    </div>
                    <div v-for="(allergy, index) in form.health.allergies" :key="index" class="flex gap-3 items-center animate-fade-in">
                        <div class="flex-1">
                            <Input v-model="form.health.allergies[index]" placeholder="e.g. Chicken, Pollen" />
                        </div>
                        <Button type="button" variant="ghost" size="icon" class="text-red-500 hover:text-red-700 hover:bg-red-50" @click="removeAllergy(index)">
                            <Trash2 class="w-4 h-4" />
                        </Button>
                    </div>
                </div>

                <!-- Veterinarian Information -->
                <div class="space-y-2 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <Label>Veterinarian Information</Label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Input id="vet-name" v-model="form.health.vetName" placeholder="Veterinarian Name" />
                        </div>
                        <div>
                            <Input id="vet-phone" v-model="form.health.vetPhone" placeholder="Phone Number" type="tel" />
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
[class*="transition"],
[class*="transform"],
[class*="scale"] {
    will-change: transform;
    transform: translateZ(0);
    -webkit-transform: translateZ(0);
}
</style>
