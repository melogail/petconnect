<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/** One selectable value in a filter group. */
export type FilterOption = {
    value: string | number;
    label: string;
};

/**
 * A list of checkboxes bound to an array of selected values.
 *
 * The feed filters are four of these — categories, breeds, listing types, and
 * anything a later phase adds — so the toggle bookkeeping lives here once
 * rather than four times in the sheet.
 */
const { options, modelValue, idPrefix } = defineProps<{
    options: FilterOption[];
    modelValue: (string | number)[];
    /** Distinguishes the input ids when two lists share a page. */
    idPrefix: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: (string | number)[]];
}>();

function toggle(value: string | number, checked: boolean): void {
    emit(
        'update:modelValue',
        checked
            ? [...modelValue, value]
            : modelValue.filter((selected) => selected !== value),
    );
}
</script>

<template>
    <div class="space-y-2">
        <div
            v-for="option in options"
            :key="option.value"
            class="flex items-center gap-2"
        >
            <Checkbox
                :id="`${idPrefix}-${option.value}`"
                :model-value="modelValue.includes(option.value)"
                @update:model-value="
                    (checked) => toggle(option.value, checked === true)
                "
            />
            <Label
                :for="`${idPrefix}-${option.value}`"
                class="cursor-pointer font-normal"
            >
                {{ option.label }}
            </Label>
        </div>
    </div>
</template>
