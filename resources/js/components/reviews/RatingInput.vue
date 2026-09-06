<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';

/** The star picker a review form binds to. Props in, `update:modelValue` out. */
const {
    modelValue,
    min = 1,
    max = 5,
    disabled = false,
} = defineProps<{
    modelValue: number;
    min?: number;
    max?: number;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number];
}>();

const stars = computed(() =>
    Array.from({ length: max - min + 1 }, (_, index) => min + index),
);
</script>

<template>
    <div class="flex items-center gap-1" role="radiogroup" aria-label="Rating">
        <button
            v-for="star in stars"
            :key="star"
            type="button"
            role="radio"
            :aria-checked="star === modelValue"
            :aria-label="`${star} of ${max}`"
            :disabled="disabled"
            class="focus-visible:ring-ring rounded-sm p-0.5 transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            @click="emit('update:modelValue', star)"
        >
            <Star
                class="size-6"
                :class="
                    star <= modelValue
                        ? 'fill-amber-400 text-amber-400'
                        : 'text-muted-foreground/40'
                "
            />
        </button>
    </div>
</template>
