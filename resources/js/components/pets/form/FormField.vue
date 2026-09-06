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
    <div class="grid gap-2">
        <Label :for="fieldId">
            {{ label }}
            <span v-if="required" class="text-destructive" aria-hidden="true">
                *
            </span>
        </Label>

        <slot />

        <p v-if="hint" class="text-muted-foreground text-xs">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>
