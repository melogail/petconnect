<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import ExtrasEditor from '@/components/pets/form/ExtrasEditor.vue';
import InputError from '@/components/InputError.vue';
import { petFormErrors, type PetFormState } from '@/lib/petForm';

/**
 * The free-form extras.
 *
 * `additionalInfo` is a keyed map bounded at 20 pairs, with both the keys and
 * the values capped at 255 characters — the key bound is a closure rule,
 * because Laravel has no rule that reaches array keys and the map goes straight
 * into a JSON column.
 *
 * Errors on it are keyed by the owner's own label, which is not something the
 * editor can line a message up against, so the group-level message is shown
 * above the rows.
 */
const { form } = defineProps<{ form: InertiaForm<PetFormState> }>();

const errors = computed(() => petFormErrors(form.errors));

const extrasError = computed(
    () =>
        errors.value.additionalInfo ??
        Object.entries(errors.value).find(([key]) =>
            key.startsWith('additionalInfo.'),
        )?.[1],
);
</script>

<template>
    <div class="space-y-4">
        <div>
            <h3 class="font-medium">Anything else</h3>
            <p class="text-muted-foreground text-sm">
                Up to 20 label/value pairs, in whatever words you use. A pair
                missing either half is dropped.
            </p>
        </div>

        <InputError :message="extrasError" />

        <ExtrasEditor
            :rows="form.additionalInfo"
            @update:rows="(value) => (form.additionalInfo = value)"
        />
    </div>
</template>
