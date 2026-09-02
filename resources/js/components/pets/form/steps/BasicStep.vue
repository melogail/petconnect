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
 *
 * ## Why age, weight and price are text boxes
 *
 * They were `type="number"`, which broke publishing outright: Vue's `vModelText`
 * casts unconditionally on a numeric input, so the three fields held a `number`
 * and the payload builder's `.trim()` threw before the request left the page.
 * `lib/petForm` now absorbs that at the boundary, so the crash cannot come back
 * whatever a field's `type` is — but the type itself is still wrong for these
 * three. All are decimals (`step` 0.1 and 0.01), and a numeric input hands a
 * locale-comma decimal back as an empty string, silently dropping "2,5" for
 * every Arabic and European reader of a bilingual app. It also changes the
 * value on a stray scroll wheel, in a wizard whose steps are scrolled.
 *
 * `inputmode="decimal"` keeps the numeric keypad on mobile without any of that,
 * and it is what the coordinate boxes on the location step already use. The
 * ranges the `min`/`max` attributes advertised were never the enforcement —
 * `numeric|min:0|max:99` in `PetValidationRules` is — so they move into hints.
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
            hint="A number between 0 and 99."
            required
        >
            <Input
                id="pet-age"
                v-model="form.age"
                inputmode="decimal"
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
            <Input id="pet-weight" v-model="form.weight" inputmode="decimal" />
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
            <Input id="pet-price" v-model="form.price" inputmode="decimal" />
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
