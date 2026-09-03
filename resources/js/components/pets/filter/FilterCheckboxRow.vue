<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/**
 * One labelled checkbox row — a breed, a listing type, "vaccinated only".
 *
 * The checkbox and its label are siblings joined by `id`/`for` rather than
 * nested, which is what the rest of the app does and what keeps the hit area
 * the whole row without the label swallowing clicks meant for the control.
 * `ui/checkbox` renders a `<button role="checkbox">`, and a `<button>` is a
 * labelable element, so the association carries the click.
 *
 * `checked` in, `update:checked` out. Nothing here knows what list it is part
 * of.
 */
defineProps<{
    id: string;
    label: string;
    checked: boolean;
}>();

const emit = defineEmits<{
    'update:checked': [checked: boolean];
}>();
</script>

<template>
    <div
        class="hover:bg-muted/50 flex items-center gap-3 rounded-lg p-2 transition-colors"
    >
        <Checkbox
            :id="id"
            :model-value="checked"
            @update:model-value="emit('update:checked', $event === true)"
        />
        <Label :for="id" class="min-w-0 flex-1 cursor-pointer font-normal">
            <span class="truncate">{{ label }}</span>
        </Label>
    </div>
</template>
