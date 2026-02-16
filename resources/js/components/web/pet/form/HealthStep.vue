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
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Props {
    form: any;
}

const props = defineProps<Props>();
</script>

<template>
    <div id="step-4" class="step-container animate-fade-in">
        <Card
            class="group relative overflow-hidden border-2 border-emerald-100/50 shadow-lg backdrop-blur-md transition-all duration-500 hover:border-emerald-300 hover:shadow-2xl dark:border-emerald-900/30 dark:bg-gray-800/70 dark:hover:border-emerald-700"
        >
            <!-- Animated Background Gradient -->
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-br from-emerald-50/30 via-green-50/20 to-teal-50/10 opacity-0 transition-opacity duration-700 group-hover:opacity-100 dark:from-emerald-900/20 dark:via-green-900/10 dark:to-teal-900/5"
            ></div>
            <!-- Decorative Corner -->
            <div
                class="absolute right-0 top-0 h-32 w-32 rounded-bl-full bg-gradient-to-br from-emerald-100/20 to-transparent opacity-50 dark:from-emerald-900/10"
            ></div>
            <CardHeader class="relative z-10">
                <div class="flex items-center space-x-4">
                    <div
                        class="relative rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-3 text-white shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:shadow-xl"
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
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <div>
                        <CardTitle
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                            >Health Status</CardTitle
                        >
                        <CardDescription
                            class="text-gray-500 dark:text-gray-400"
                            >Your pet's current health
                            condition</CardDescription
                        >
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="health-status">Overall Health Status</Label>
                    <Select id="health-status" v-model="form.health.status">
                        <SelectTrigger>
                            <SelectValue placeholder="Select health status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="excellent">Excellent</SelectItem>
                            <SelectItem value="good">Good</SelectItem>
                            <SelectItem value="fair">Fair</SelectItem>
                            <SelectItem value="poor">Poor</SelectItem>
                            <SelectItem value="critical">Critical</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="vaccinated"
                            v-model:checked="form.health.vaccinated"
                        />
                        <Label for="vaccinated">Vaccinated</Label>
                    </div>

                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="spayed-neutered"
                            v-model:checked="form.health.spayedNeutered"
                        />
                        <Label for="spayed-neutered">Spayed/Neutered</Label>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="last-vet-visit">Last Vet Visit</Label>
                    <Input
                        id="last-vet-visit"
                        type="date"
                        v-model="form.health.lastVetVisit"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="special-needs"
                        >Special Needs or Medical Conditions</Label
                    >
                    <Textarea
                        id="special-needs"
                        v-model="form.health.specialNeeds"
                        placeholder="Any special care requirements, medications, or health concerns..."
                        class="min-h-[100px]"
                    />
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
