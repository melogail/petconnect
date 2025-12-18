<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Plus, X } from 'lucide-vue-next';

interface Props {
    form: any;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    addInfoField: [];
    removeInfoField: [index: number];
}>();
</script>

<template>
    <div id="step-6" class="step-container animate-fade-in">
        <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-indigo-100/50 dark:border-indigo-900/30 hover:border-indigo-300 dark:hover:border-indigo-700 shadow-lg">
            <!-- Animated Background Gradient -->
            <div class="absolute -z-10 inset-0 bg-gradient-to-br from-indigo-50/30 via-blue-50/20 to-cyan-50/10 dark:from-indigo-900/20 dark:via-blue-900/10 dark:to-cyan-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
            <!-- Decorative Corner -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-indigo-100/20 to-transparent dark:from-indigo-900/10 rounded-bl-full opacity-50"></div>
            <CardHeader class="relative z-10">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <div class="relative p-3 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                            <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Additional Information</CardTitle>
                            <CardDescription class="text-gray-500 dark:text-gray-400">Add any extra details about your pet</CardDescription>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="emit('addInfoField')"
                        class="relative overflow-hidden group"
                    >
                        <span class="relative z-10 flex items-center">
                            <Plus class="w-4 h-4 mr-2 transition-transform group-hover:rotate-90" />
                            Add Field
                        </span>
                        <span class="absolute inset-0 bg-indigo-50 dark:bg-indigo-900/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-for="(info, index) in form.additionalInfo" :key="index" class="grid grid-cols-12 gap-3 items-end">
                    <div class="col-span-5">
                        <Label :for="`key-${index}`">Key</Label>
                        <Input
                            :id="`key-${index}`"
                            v-model="info.key"
                            placeholder="e.g., Microchip ID, Color"
                        />
                    </div>
                    <div class="col-span-5">
                        <Label :for="`value-${index}`">Value</Label>
                        <Input
                            :id="`value-${index}`"
                            v-model="info.value"
                            placeholder="Enter value"
                        />
                    </div>
                    <div class="col-span-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="w-full h-10 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 hover:border-red-200 dark:hover:border-red-800 transition-all duration-200 group/btn"
                            @click="emit('removeInfoField', index)"
                            :disabled="form.additionalInfo.length === 1"
                        >
                            <X class="w-4 h-4 transition-transform group-hover/btn:rotate-90" />
                        </Button>
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
