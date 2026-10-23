<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import FormField from '@/components/pets/form/FormField.vue';
import SelectInput from '@/components/pets/form/SelectInput.vue';
import type { SelectInputOption } from '@/components/pets/form/SelectInput.vue';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/composables/useLocale';
import { petFormErrors, type PetFormState } from '@/lib/petForm';
import { taxonomyName } from '@/lib/taxonomy';
import type {
    PetFormOptions,
    PetGender,
    PetListingType,
    PetStatus,
} from '@/types';
import { useTranslations } from '@/composables/useTranslations';

/**
 * Identity, taxonomy and listing terms.
 *
 * `breed_id` is only accepted when it belongs to the submitted category — the
 * Form Request scopes its `exists` rule that way — so choosing a category
 * clears the breed rather than leaving a mismatched pair on the wire.
 *
 * `price` is `required_if:listing_type,sale` and `present|nullable` otherwise.
 * The box is only on screen for a sale — adoption and mating are free by
 * definition and the pipeline drops a price from them anyway — and it is
 * emptied when the listing type moves away from sale, so a figure typed
 * before the change cannot linger in the state. `toPetPayload()` still posts
 * the key as null, which is what `present` needs.
 *
 * ## Litters
 *
 * An **adoption** listing may offer more than one animal. Picking adoption
 * reveals the quantity box; a quantity above 1 reveals the male and female
 * counts, makes the name optional (a litter has no single name) and adds
 * `mixed` as a meaningful gender. The backend holds the rules — only adoption
 * may exceed 1, the counts must add up to the quantity, the gender must agree
 * with the counts — and reports them on `quantity` and `gender`; this step
 * only decides what is on screen. Switching away from adoption snaps the
 * quantity back to 1 so a stale litter cannot be posted on a sale.
 *
 * ## Why weight, price and the counts are text boxes
 *
 * They were `type="number"`, which broke publishing outright: Vue's `vModelText`
 * casts unconditionally on a numeric input, so the fields held a `number` and
 * the payload builder's `.trim()` threw before the request left the page.
 * `lib/petForm` now absorbs that at the boundary, so the crash cannot come back
 * whatever a field's `type` is — but the type itself is still wrong for the
 * decimals. A numeric input hands a locale-comma decimal back as an empty
 * string, silently dropping "2,5" for every Arabic and European reader of a
 * bilingual app. It also changes the value on a stray scroll wheel, in a wizard
 * whose steps are scrolled.
 *
 * `inputmode="decimal"` / `inputmode="numeric"` keep the numeric keypad on
 * mobile without any of that, and it is what the coordinate boxes on the
 * location step already use. The ranges the `min`/`max` attributes advertised
 * were never the enforcement — `PetValidationRules` is — so they live in hints.
 */
const { form, options } = defineProps<{
    form: InertiaForm<PetFormState>;
    options: PetFormOptions;
}>();

const errors = computed(() => petFormErrors(form.errors));

const { locale } = useLocale();

const { t } = useTranslations();

/**
 * Both selects label their options with `taxonomyName`, never the raw `name`:
 * the option resources ship `name` and `name_ar` on every row and the reader's
 * language decides. The submitted value is the id either way.
 */
const categoryOptions = computed<SelectInputOption[]>(() =>
    options.categories.map((category) => ({
        value: String(category.id),
        label: taxonomyName(category, locale.value.current),
    })),
);

const breedOptions = computed<SelectInputOption[]>(() =>
    (
        options.categories.find((category) => category.id === form.category_id)
            ?.breeds ?? []
    ).map((breed) => ({
        value: String(breed.id),
        label: taxonomyName(breed, locale.value.current),
    })),
);

const isSale = computed(() => form.listing_type === 'sale');
const isAdoption = computed(() => form.listing_type === 'adoption');

/** More than one animal on offer: only an adoption listing can be. */
const isLitter = computed(() => isAdoption.value && Number(form.quantity) > 1);

/** Today as `Y-m-d`, the ceiling for the birth-date picker. */
const today = new Date().toISOString().slice(0, 10);

watch(isAdoption, (adoption) => {
    if (!adoption) {
        form.quantity = 1;
    }
});

watch(isSale, (sale) => {
    if (!sale) {
        form.price = '';
    }
});

watch(isLitter, (litter) => {
    if (!litter) {
        form.male_count = '';
        form.female_count = '';

        if (form.gender === 'mixed') {
            form.gender = '';
        }
    }
});

function pickCategory(value: string): void {
    form.category_id = value === '' ? null : Number(value);
    form.breed_id = null;
}
</script>

<template>
    <div class="grid gap-5 sm:grid-cols-2">
        <FormField
            :label="t('wizard.name')"
            field-id="pet-name"
            :error="errors.name"
            :required="!isLitter"
            :hint="isLitter ? t('wizard.optional_for_litter') : undefined"
            class="sm:col-span-2"
        >
            <Input
                id="pet-name"
                v-model="form.name"
                maxlength="255"
                :required="!isLitter"
            />
        </FormField>

        <FormField
            :label="t('wizard.category')"
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
                :placeholder="t('wizard.pick_category')"
                @update:model-value="pickCategory"
            />
        </FormField>

        <FormField
            :label="t('wizard.breed')"
            field-id="pet-breed"
            :error="errors.breed_id"
            :hint="
                form.category_id === null
                    ? t('wizard.pick_category_first')
                    : undefined
            "
        >
            <SelectInput
                id="pet-breed"
                :model-value="
                    form.breed_id === null ? '' : String(form.breed_id)
                "
                :options="breedOptions"
                :placeholder="t('wizard.any_breed')"
                :disabled="breedOptions.length === 0"
                @update:model-value="
                    (value) =>
                        (form.breed_id = value === '' ? null : Number(value))
                "
            />
        </FormField>

        <FormField
            :label="t('wizard.birth_date')"
            field-id="pet-birth-date"
            :error="errors.birth_date"
            :hint="t('wizard.birth_date_hint')"
        >
            <Input
                id="pet-birth-date"
                v-model="form.birth_date"
                type="date"
                :max="today"
            />
        </FormField>

        <FormField
            :label="t('wizard.gender')"
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
                :placeholder="t('wizard.pick_gender')"
            />
        </FormField>

        <FormField
            :label="t('wizard.colour')"
            field-id="pet-color"
            :error="errors.color"
        >
            <Input id="pet-color" v-model="form.color" maxlength="255" />
        </FormField>

        <FormField
            :label="t('wizard.weight_kg')"
            field-id="pet-weight"
            :error="errors.weight"
            :hint="t('wizard.weight_hint')"
        >
            <Input id="pet-weight" v-model="form.weight" inputmode="decimal" />
        </FormField>

        <FormField
            :label="t('wizard.listing_type')"
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
                :placeholder="t('wizard.pick_listing_type')"
            />
        </FormField>

        <FormField
            v-if="isAdoption"
            :label="t('wizard.quantity')"
            field-id="pet-quantity"
            :error="errors.quantity"
            :hint="t('wizard.quantity_hint')"
            required
        >
            <Input
                id="pet-quantity"
                v-model="form.quantity"
                inputmode="numeric"
                required
            />
        </FormField>

        <template v-if="isLitter">
            <FormField
                :label="t('wizard.males')"
                field-id="pet-male-count"
                :error="errors.male_count"
                :hint="t('wizard.counts_hint')"
                required
            >
                <Input
                    id="pet-male-count"
                    v-model="form.male_count"
                    inputmode="numeric"
                    required
                />
            </FormField>

            <FormField
                :label="t('wizard.females')"
                field-id="pet-female-count"
                :error="errors.female_count"
                required
            >
                <Input
                    id="pet-female-count"
                    v-model="form.female_count"
                    inputmode="numeric"
                    required
                />
            </FormField>
        </template>

        <FormField
            v-if="isSale"
            :label="t('pets.price')"
            field-id="pet-price"
            :error="errors.price"
            :hint="t('wizard.price_hint')"
            required
        >
            <Input
                id="pet-price"
                v-model="form.price"
                inputmode="decimal"
                required
            />
        </FormField>

        <FormField
            :label="t('wizard.status')"
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
                :placeholder="t('wizard.select_status')"
            />
        </FormField>
    </div>
</template>
