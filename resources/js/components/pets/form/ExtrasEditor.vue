<script setup lang="ts">
import RepeaterShell from '@/components/pets/form/RepeaterShell.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PetExtraRow } from '@/lib/petForm';

/**
 * The free-form extras: whatever label/value pairs the owner wants to add.
 *
 * The rows are ordered here only so the editor can render them; the wire shape
 * is a **keyed map** (`additionalInfo: {label: value}`) and `toPetPayload()`
 * folds them into it. That is the fix for the legacy form, which posted
 * `[{key, value}]` and then string-matched the keys against hardcoded English
 * labels — so a listing written in Arabic showed none of its own extras back.
 *
 * A pair missing either half is dropped by the payload builder and by the
 * pipeline, so a half-typed row never becomes a record.
 */
const { rows } = defineProps<{
    rows: PetExtraRow[];
}>();

const emit = defineEmits<{
    'update:rows': [value: PetExtraRow[]];
}>();

function patch(index: number, key: keyof PetExtraRow, value: string): void {
    emit(
        'update:rows',
        rows.map((row, position) =>
            position === index ? { ...row, [key]: value } : row,
        ),
    );
}

function add(): void {
    emit('update:rows', [...rows, { label: '', value: '' }]);
}

function remove(index: number): void {
    emit(
        'update:rows',
        rows.filter((_, position) => position !== index),
    );
}
</script>

<template>
    <RepeaterShell
        :rows="rows"
        add-label="Add a detail"
        empty-label="Nothing extra yet — add anything a buyer would ask about."
        @add="add"
        @remove="remove"
    >
        <template #row="{ index }">
            <div class="grid gap-2">
                <Label :for="`extra-label-${index}`">Label</Label>
                <Input
                    :id="`extra-label-${index}`"
                    :model-value="rows[index].label"
                    placeholder="Good with children"
                    @update:model-value="
                        (value) => patch(index, 'label', String(value))
                    "
                />
            </div>
            <div class="grid gap-2">
                <Label :for="`extra-value-${index}`">Value</Label>
                <Input
                    :id="`extra-value-${index}`"
                    :model-value="rows[index].value"
                    placeholder="Yes"
                    @update:model-value="
                        (value) => patch(index, 'value', String(value))
                    "
                />
            </div>
        </template>
    </RepeaterShell>
</template>
