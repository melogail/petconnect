<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/** One entry in the list. Values are strings; numeric ids are stringified. */
export type SelectInputOption = {
    value: string;
    label: string;
};

/**
 * A single-choice select bound to a string.
 *
 * Every enum-backed field on the pet form is one of these, and so are the
 * category and breed pickers — those hold ids, so the caller converts on both
 * edges rather than teaching the control about numbers.
 */
defineProps<{
    modelValue: string;
    options: SelectInputOption[];
    id: string;
    placeholder: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <Select
        :model-value="modelValue"
        :disabled="disabled"
        @update:model-value="
            (value) => emit('update:modelValue', String(value ?? ''))
        "
    >
        <SelectTrigger :id="id" class="w-full">
            <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
