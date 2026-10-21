<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

/**
 * A labelled control with its validation message.
 *
 * The pet form has roughly thirty of these, so the label/control/error triple
 * lives here once. `error` takes the message straight off `form.errors`, which
 * for the nested groups is keyed by dot path (`location.city`,
 * `health.vaccinations.0.name`).
 *
 * The rows are `auto auto 1fr`: label, control, then the hint and error in one
 * cell that takes whatever height the grid row has left. The steps lay these
 * out two to a row, and a plain `grid gap-2` stretched all three rows evenly,
 * so a field beside one with a hint had its control pushed a few pixels down
 * and the two boxes never lined up. Pinning the first two rows keeps every
 * control on the same baseline whatever sits under its neighbour. The slot
 * is wrapped so a control made of several elements still fills one row.
 */
defineProps<{
    label: string;
    /** The id of the control inside the slot, for the label's `for`. */
    fieldId?: string;
    error?: string;
    hint?: string;
    required?: boolean;
}>();
</script>

<template>
    <div class="grid grid-rows-[auto_auto_1fr] gap-2">
        <Label :for="fieldId">
            {{ label }}
            <span v-if="required" class="text-destructive" aria-hidden="true">
                *
            </span>
        </Label>

        <div>
            <slot />
        </div>

        <div class="space-y-1">
            <p v-if="hint" class="text-muted-foreground text-xs">{{ hint }}</p>
            <InputError :message="error" />
        </div>
    </div>
</template>
