<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed, type Component } from 'vue';

/** One step of the wizard, as the stepper renders it. */
export type WizardStep = {
    id: number;
    title: string;
    icon: Component;
};

/**
 * The wizard's progress indicator.
 *
 * Deliberately plain. The legacy stepper carried a per-step gradient, a pulse,
 * a bounce and a scale transform — eight colour schemes and fifteen animations
 * for a progress bar — and its own notes called the result styling debt. What
 * a stepper owes the user is where they are, where they have been, and what is
 * wrong; everything below is one of those three.
 *
 * Steps are all reachable: the backend validates on submit and the form is one
 * request, so blocking navigation would only stop somebody filling the form in
 * the order that suits them.
 */
const { steps, current, visited, invalid } = defineProps<{
    steps: WizardStep[];
    current: number;
    /** Steps the user has already been on. */
    visited: number[];
    /** Steps holding a field the server rejected. */
    invalid: number[];
}>();

const emit = defineEmits<{ select: [step: number] }>();

const active = computed(
    () => steps.find((step) => step.id === current) ?? steps[0],
);

const progress = computed(() => Math.round((current / steps.length) * 100));
</script>

<template>
    <div class="space-y-3">
        <!-- Compact on small screens: eight labelled circles do not fit. -->
        <div class="sm:hidden">
            <p class="text-sm font-medium">
                Step {{ current }} of {{ steps.length }} — {{ active.title }}
            </p>
        </div>

        <ol class="hidden flex-wrap items-center gap-1 sm:flex">
            <li v-for="step in steps" :key="step.id">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-sm transition-colors"
                    :class="[
                        step.id === current
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-accent',
                        invalid.includes(step.id) && step.id !== current
                            ? 'text-destructive'
                            : '',
                    ]"
                    :aria-current="step.id === current ? 'step' : undefined"
                    @click="emit('select', step.id)"
                >
                    <Check
                        v-if="
                            visited.includes(step.id) &&
                            step.id !== current &&
                            !invalid.includes(step.id)
                        "
                        class="size-4"
                    />
                    <component :is="step.icon" v-else class="size-4" />
                    <span>{{ step.title }}</span>
                </button>
            </li>
        </ol>

        <div
            class="bg-muted h-1.5 w-full overflow-hidden rounded-full"
            role="progressbar"
            :aria-valuenow="progress"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="bg-primary h-full transition-all"
                :style="{ width: `${progress}%` }"
            />
        </div>
    </div>
</template>
