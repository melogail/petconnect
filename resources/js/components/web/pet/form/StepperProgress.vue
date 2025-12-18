<script setup lang="ts">
import { Check } from 'lucide-vue-next';

interface Step {
    id: number;
    name: string;
    icon: any;
    description: string;
}

interface Props {
    steps: Step[];
    currentStep: number;
    totalSteps: number;
    completedSteps: number[];
    invalidSteps?: number[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    goToStep: [stepId: number];
}>();
</script>

<template>
    <div class="mb-12">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold bg-gradient-to-r from-primary-600 via-purple-600 to-pink-600 bg-clip-text text-transparent mb-2">
                Add New Pet
            </h1>
            <p class="text-gray-600 dark:text-gray-400">Complete all steps to create your pet listing</p>
        </div>

        <!-- Desktop Stepper -->
        <div class="hidden md:block">
            <div class="relative">
                <!-- Progress Line -->
                <div class="absolute top-5 left-0 right-0 h-0.5 bg-gray-200 dark:bg-gray-700"></div>
                <div 
                    class="absolute top-5 left-0 h-0.5 bg-gradient-to-r from-primary-500 to-purple-500 transition-all duration-500 ease-out"
                    :style="{ width: `${((currentStep - 1) / (totalSteps - 1)) * 100}%` }"
                ></div>

                <!-- Steps -->
                <div class="relative flex justify-between">
                    <div 
                        v-for="step in steps" 
                        :key="step.id"
                        class="flex flex-col items-center group cursor-pointer"
                        @click="emit('goToStep', step.id)"
                    >
                        <!-- Step Circle -->
                        <div 
                            class="w-12 h-12 rounded-full flex items-center justify-center transition-all duration-300 mb-2 relative"
                            :class="{
                                'bg-gradient-to-br from-primary-500 via-purple-500 to-pink-500 text-white shadow-xl scale-110 ring-4 ring-primary-100 dark:ring-primary-900/50': currentStep === step.id && !invalidSteps?.includes(step.id),
                                'bg-gradient-to-br from-red-500 to-rose-600 text-white shadow-xl scale-110 ring-4 ring-red-100 dark:ring-red-900/50': invalidSteps?.includes(step.id),
                                'bg-gradient-to-br from-green-500 to-emerald-600 text-white shadow-lg ring-2 ring-green-200 dark:ring-green-900/50': completedSteps.includes(step.id) && currentStep !== step.id && !invalidSteps?.includes(step.id),
                                'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500': !completedSteps.includes(step.id) && currentStep !== step.id && !invalidSteps?.includes(step.id),
                                'group-hover:scale-110 group-hover:shadow-lg': true
                            }"
                        >
                            <!-- Pulse animation for completed steps -->
                            <div v-if="completedSteps.includes(step.id) && currentStep !== step.id" class="absolute inset-0 rounded-full bg-green-400 animate-ping opacity-20"></div>
                            
                            <Check v-if="completedSteps.includes(step.id) && currentStep !== step.id" class="w-6 h-6 relative z-10 animate-bounce-once" />
                            <component v-else :is="step.icon" class="w-5 h-5 relative z-10" />
                        </div>

                        <!-- Step Label -->
                        <div class="text-center">
                            <div 
                                class="text-sm font-medium transition-colors duration-200"
                                :class="{
                                    'text-primary-600 dark:text-primary-400': currentStep === step.id && !invalidSteps?.includes(step.id),
                                    'text-red-600 dark:text-red-400': invalidSteps?.includes(step.id),
                                    'text-gray-700 dark:text-gray-300': completedSteps.includes(step.id) && currentStep !== step.id && !invalidSteps?.includes(step.id),
                                    'text-gray-400 dark:text-gray-500': !completedSteps.includes(step.id) && currentStep !== step.id && !invalidSteps?.includes(step.id)
                                }"
                            >
                                {{ step.name }}
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-600 mt-0.5">
                                {{ step.description }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Stepper -->
        <div class="md:hidden">
            <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary-500 to-purple-500 flex items-center justify-center text-white">
                            <component :is="steps[currentStep - 1].icon" class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-white">
                                {{ steps[currentStep - 1].name }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Step {{ currentStep }} of {{ totalSteps }}
                            </div>
                        </div>
                    </div>
                    <div class="text-sm font-medium text-primary-600 dark:text-primary-400">
                        {{ Math.round((currentStep / totalSteps) * 100) }}%
                    </div>
                </div>
                <!-- Progress Bar -->
                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div 
                        class="h-full bg-gradient-to-r from-primary-500 to-purple-500 transition-all duration-500"
                        :style="{ width: `${(currentStep / totalSteps) * 100}%` }"
                    ></div>
                </div>
            </div>
        </div>
    </div>
</template>
