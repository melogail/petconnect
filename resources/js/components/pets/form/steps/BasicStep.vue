<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/pets/form/FormField.vue';
import SelectInput from '@/components/pets/form/SelectInput.vue';
import type { SelectInputOption } from '@/components/pets/form/SelectInput.vue';
import { Input } from '@/components/ui/input';
import { petFormErrors, type PetFormState } from '@/lib/petForm';
import type {
    PetFormOptions,
    PetGender,
    PetListingType,
    PetStatus,
} from '@/types';

/**
 * Identity, taxonomy and listing terms.
 *
 * `breed_id` is only accepted when it belongs to the submitted category — the
 * Form Request scopes its `exists` rule that way — so choosing a category
 * clears the breed rather than leaving a mismatched pair on the wire.
 *
 * `price` is `required_if:listing_type,sale` and `present|nullable` otherwise,
 * so the field stays on the form for every listing type and only its label
 * changes.
 */
const { form, options } = defineProps<{
    form: InertiaForm<PetFormState>;
    options: PetFormOptions;
}>();

const errors = computed(() => petFormErrors(form.errors));

const categoryOptions = computed<SelectInputOption[]>(() =>
    options.categories.map((category) => ({
        value: String(category.id),
        label: category.name,
    })),
);

const breedOptions = computed<SelectInputOption[]>(() =>
    (
        options.categories.find((category) => category.id === form.category_id)
            ?.breeds ?? []
    ).map((breed) => ({ value: String(breed.id), label: breed.name })),
);

const isSale = computed(() => form.listing_type === 'sale');

function pickCategory(value: string): void {
    form.category_id = value === '' ? null : Number(value);
    form.breed_id = null;
}
</script>

<template>
    <div class="grid gap-5 sm:grid-cols-2">
        <FormField
            label="Name"
            field-id="pet-name"
            :error="errors.name"
            required
            class="sm:col-span-2"
        >
            <Input id="pet-name" v-model="form.name" maxlength="255" required />
        </FormField>

        <FormField
            label="Category"
            field-id="pet-category"
            :error="errors.category_id"
            required
        >
            <SelectInput
                id="pet-category"
                :model-value="
                    form.category_id === null ? '' : String(form.category_id)
                "
                :options="categoryOptions"
                placeholder="Pick a category"
                @update:model-value="pickCategory"
            />
        </FormField>

        <FormField
            label="Breed"
            field-id="pet-breed"
            :error="errors.breed_id"
            :hint="
                form.category_id === null ? 'Pick a category first.' : undefined
            "
        >
            <SelectInput
                id="pet-breed"
                :model-value="
                    form.breed_id === null ? '' : String(form.breed_id)
                "
                :options="breedOptions"
                placeholder="Any breed"
                :disabled="breedOptions.length === 0"
                @update:model-value="
                    (value) =>
                        (form.breed_id = value === '' ? null : Number(value))
                "
            />
        </FormField>

        <FormField
            label="Age (years)"
            field-id="pet-age"
            :error="errors.age"
            required
        >
            <Input
                id="pet-age"
                v-model="form.age"
                type="number"
                min="0"
                max="99"
                step="0.1"
                required
            />
        </FormField>

        <FormField
            label="Gender"
            field-id="pet-gender"
            :error="errors.gender"
            required
        >
            <SelectInput
                id="pet-gender"
                :model-value="form.gender"
                @update:model-value="
                    (value) => (form.gender = value as PetGender | '')
                "
                :options="options.genders"
                placeholder="Pick a gender"
            />
        </FormField>

        <FormField
            label="Colour"
            field-id="pet-color"
            :error="errors.color"
            required
        >
            <Input
                id="pet-color"
                v-model="form.color"
                maxlength="255"
                required
            />
        </FormField>

        <FormField
            label="Weight (kg)"
            field-id="pet-weight"
            :error="errors.weight"
            hint="Leave blank if you would rather not say."
        >
            <Input
                id="pet-weight"
                v-model="form.weight"
                type="number"
                min="0"
                step="0.01"
            />
        </FormField>

        <FormField
            label="Listing type"
            field-id="pet-listing-type"
            :error="errors.listing_type"
            required
        >
            <SelectInput
                id="pet-listing-type"
                :model-value="form.listing_type"
                @update:model-value="
                    (value) =>
                        (form.listing_type = value as PetListingType | '')
                "
                :options="options.listingTypes"
                placeholder="Pick a listing type"
            />
        </FormField>

        <FormField
            label="Price"
            field-id="pet-price"
            :error="errors.price"
            :required="isSale"
            :hint="isSale ? undefined : 'Optional unless this is a sale.'"
        >
            <Input
                id="pet-price"
                v-model="form.price"
                type="number"
                min="0"
                step="0.01"
            />
        </FormField>

        <FormField
            label="Status"
            field-id="pet-status"
            :error="errors.status"
            required
        >
            <SelectInput
                id="pet-status"
                :model-value="form.status"
                @update:model-value="
                    (value) => (form.status = value as PetStatus | '')
                "
                :options="options.statuses"
                placeholder="Pick a status"
            />
        </FormField>
    </div>
</template>
