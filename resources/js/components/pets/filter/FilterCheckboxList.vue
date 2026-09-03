<script setup lang="ts" generic="TValue extends string | number">
import FilterCheckboxRow from '@/components/pets/filter/FilterCheckboxRow.vue';

/** One selectable value in a filter group. */
export type FilterOption<T extends string | number> = {
    value: T;
    label: string;
};

/**
 * A group of checkboxes bound to an array of selected values.
 *
 * Two callers — the breeds of one category, and the listing types — so the
 * toggle bookkeeping lives here once. It is generic over the value type because
 * those two are `number` and `string`: widening to `string | number` would push
 * a cast back onto both call sites, and the breed list's array goes straight
 * into `CategorySelection`, which is keyed and typed by number.
 *
 * The emitted array is rebuilt from `options`, so it is always a subset of what
 * was offered and always in the offered order. That is what upholds
 * `CategorySelection`'s invariant that a category's entry holds only its own
 * breed ids: this cannot emit a value it was not given.
 */
const { options, modelValue, idPrefix } = defineProps<{
    options: FilterOption<TValue>[];
    modelValue: TValue[];
    /** Distinguishes the input ids when two groups share a page. */
    idPrefix: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: TValue[]];
}>();

function toggle(value: TValue, checked: boolean): void {
    const selected = new Set(modelValue);

    if (checked) {
        selected.add(value);
    } else {
        selected.delete(value);
    }

    emit(
        'update:modelValue',
        options.map((option) => option.value).filter((it) => selected.has(it)),
    );
}
</script>

<template>
    <div class="space-y-1">
        <FilterCheckboxRow
            v-for="option in options"
            :id="`${idPrefix}-${option.value}`"
            :key="option.value"
            :label="option.label"
            :checked="modelValue.includes(option.value)"
            @update:checked="toggle(option.value, $event)"
        />
    </div>
</template>
