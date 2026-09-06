<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/pets/form/FormField.vue';
import TagInput from '@/components/pets/form/TagInput.vue';
import { Textarea } from '@/components/ui/textarea';
import { petFormErrors, type PetFormState } from '@/lib/petForm';

/**
 * The description and the personality traits.
 *
 * `traits` is a collection key with no `present` rule, so an empty list is sent
 * as `null` rather than `[]` — over multipart the two are indistinguishable and
 * `[]` would arrive as a missing key. `toPetPayload()` owns that.
 */
const { form } = defineProps<{ form: InertiaForm<PetFormState> }>();

const errors = computed(() => petFormErrors(form.errors));

const traitError = computed(
    () =>
        errors.value.traits ??
        Object.entries(errors.value).find(([key]) =>
            key.startsWith('traits.'),
        )?.[1],
);
</script>

<template>
    <div class="space-y-5">
        <FormField
            label="Description"
            field-id="pet-description"
            :error="errors.description"
            hint="Up to 5000 characters."
            required
        >
            <Textarea
                id="pet-description"
                v-model="form.description"
                rows="8"
                maxlength="5000"
                required
            />
        </FormField>

        <FormField
            label="Personality traits"
            field-id="pet-traits"
            :error="traitError"
            hint="Up to 20. Type whatever fits — there is no fixed list."
        >
            <TagInput
                v-model="form.traits"
                input-id="pet-traits"
                placeholder="Playful, quiet, good on a lead…"
            />
        </FormField>
    </div>
</template>
