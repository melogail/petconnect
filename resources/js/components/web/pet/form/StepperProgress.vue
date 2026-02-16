<script setup lang="ts">
import { Check, AlertCircle } from 'lucide-vue-next';

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
            <h1
                class="mb-2 bg-gradient-to-r from-primary-600 via-purple-600 to-pink-600 bg-clip-text text-4xl font-bold text-transparent"
            >
                Add New Pet
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                Complete all steps to create your pet listing
            </p>
        </div>

        <!-- Desktop Stepper -->
        <div class="hidden md:block">
            <div class="relative">
                <!-- Progress Line -->
                <div
                    class="absolute left-0 right-0 top-5 h-0.5 bg-gray-200 dark:bg-gray-700"
                ></div>
                <div
                    class="absolute left-0 top-5 h-0.5 bg-gradient-to-r from-primary-500 to-purple-500 transition-all duration-500 ease-out"
                    :style="{
                        width: `${((currentStep - 1) / (totalSteps - 1)) * 100}%`,
                    }"
                ></div>

                <!-- Steps -->
                <div class="relative flex justify-between">
                    <div
                        v-for="step in steps"
                        :key="step.id"
                        class="group flex cursor-pointer flex-col items-center"
                        @click="emit('goToStep', step.id)"
                    >
                        <!-- Step Circle -->
                        <div
                            class="relative mb-2 flex h-12 w-12 items-center justify-center rounded-full transition-all duration-300"
                            :class="{
                                'scale-110 bg-gradient-to-br from-primary-500 via-purple-500 to-pink-500 text-white shadow-xl ring-4 ring-primary-100 dark:ring-primary-900/50':
                                    currentStep === step.id &&
                                    !invalidSteps?.includes(step.id),
                                'scale-110 bg-gradient-to-br from-red-500 to-rose-600 text-white shadow-xl ring-4 ring-red-100 dark:ring-red-900/50':
                                    invalidSteps?.includes(step.id),
                                'bg-gradient-to-br from-green-500 to-emerald-600 text-white shadow-lg ring-2 ring-green-200 dark:ring-green-900/50':
                                    completedSteps.includes(step.id) &&
                                    currentStep !== step.id &&
                                    !invalidSteps?.includes(step.id),
                                'bg-gray-200 text-gray-400 dark:bg-gray-700 dark:text-gray-500':
                                    !completedSteps.includes(step.id) &&
                                    currentStep !== step.id &&
                                    !invalidSteps?.includes(step.id),
                                'group-hover:scale-110 group-hover:shadow-lg': true,
                            }"
                        >
                            <!-- Pulse animation for completed steps -->
                            <div
                                v-if="
                                    completedSteps.includes(step.id) &&
                                    currentStep !== step.id &&
                                    !invalidSteps?.includes(step.id)
                                "
                                class="absolute inset-0 animate-ping rounded-full bg-green-400 opacity-20"
                            ></div>

                            <!-- Pulse animation for invalid steps -->
                            <div
                                v-if="invalidSteps?.includes(step.id)"
                                class="absolute inset-0 animate-ping rounded-full bg-red-400 opacity-20"
                            ></div>

                            <!-- Error icon for invalid steps -->
                            <AlertCircle
                                v-if="invalidSteps?.includes(step.id)"
                                class="relative z-10 h-6 w-6"
                            />
                            <!-- Check icon for completed steps -->
                            <Check
                                v-else-if="
                                    completedSteps.includes(step.id) &&
                                    currentStep !== step.id
                                "
                                class="animate-bounce-once relative z-10 h-6 w-6"
                            />
                            <!-- Regular step icon -->
                            <component
                                v-else
                                :is="step.icon"
                                class="relative z-10 h-5 w-5"
                            />
                        </div>

                        <!-- Step Label -->
                        <div class="text-center">
                            <div
                                class="text-sm font-medium transition-colors duration-200"
                                :class="{
                                    'text-primary-600 dark:text-primary-400':
                                        currentStep === step.id &&
                                        !invalidSteps?.includes(step.id),
                                    'text-red-600 dark:text-red-400':
                                        invalidSteps?.includes(step.id),
                                    'text-gray-700 dark:text-gray-300':
                                        completedSteps.includes(step.id) &&
                                        currentStep !== step.id &&
                                        !invalidSteps?.includes(step.id),
                                    'text-gray-400 dark:text-gray-500':
                                        !completedSteps.includes(step.id) &&
                                        currentStep !== step.id &&
                                        !invalidSteps?.includes(step.id),
                                }"
                            >
                                {{ step.name }}
                            </div>
                            <div
                                class="mt-0.5 text-xs text-gray-400 dark:text-gray-600"
                            >
                                {{ step.description }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Stepper -->
        <div class="md:hidden">
            <div
                class="rounded-xl border border-gray-100 bg-white/80 p-4 shadow-sm backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800/80"
            >
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-r from-primary-500 to-purple-500 text-white"
                        >
                            <component
                                :is="steps[currentStep - 1].icon"
                                class="h-5 w-5"
                            />
                        </div>
                        <div>
                            <div
                                class="text-sm font-semibold text-gray-800 dark:text-white"
                            >
                                {{ steps[currentStep - 1].name }}
                            </div>
                            <div
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                Step {{ currentStep }} of {{ totalSteps }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="text-sm font-medium text-primary-600 dark:text-primary-400"
                    >
                        {{ Math.round((currentStep / totalSteps) * 100) }}%
                    </div>
                </div>
                <!-- Progress Bar -->
                <div
                    class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                >
                    <div
                        class="h-full bg-gradient-to-r from-primary-500 to-purple-500 transition-all duration-500"
                        :style="{
                            width: `${(currentStep / totalSteps) * 100}%`,
                        }"
                    ></div>
                </div>
            </div>
        </div>
    </div>
</template>
