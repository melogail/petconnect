<script setup lang="ts">
import { computed } from 'vue';
import FilterCheckboxList from '@/components/pets/filter/FilterCheckboxList.vue';
import type { FilterOption } from '@/components/pets/filter/FilterCheckboxList.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { taxonomyName } from '@/lib/taxonomy';
import type { PetBreedOption } from '@/types';

/**
 * The breeds of one category, revealed when its row is expanded.
 *
 * The indent rail is `ms-10 border-s ps-3` — logical properties, so it hangs
 * off the start edge in both directions and lines up under the category's
 * disclosure arrow either way.
 *
 * "Unselect all" has no "select all" twin, and that is not an omission: the
 * parent checkbox already selects the whole category in one click, so a second
 * control for it would be a second way to reach the same state. Clearing,
 * though, is not reachable from the parent without first passing through
 * "everything selected", which is why this one earns its place.
 */
const { breeds, modelValue } = defineProps<{
    breeds: PetBreedOption[];
    modelValue: number[];
    /** Distinguishes the checkbox ids from the next category's. */
    idPrefix: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const options = computed<FilterOption<number>[]>(() =>
    breeds.map((breed) => ({
        value: breed.id,
        label: taxonomyName(breed, locale.value.current),
    })),
);
</script>

<template>
    <div class="border-border ms-10 space-y-1 border-s py-1 ps-3">
        <div class="flex items-center justify-between gap-2 px-2 py-1">
            <button
                type="button"
                class="text-primary disabled:text-muted-foreground rounded-sm text-xs font-medium transition-colors hover:underline disabled:cursor-not-allowed disabled:no-underline"
                :disabled="modelValue.length === 0"
                @click="emit('update:modelValue', [])"
            >
                {{ t('home.unselect_all') }}
            </button>
        </div>

        <FilterCheckboxList
            :options="options"
            :model-value="modelValue"
            :id-prefix="idPrefix"
            @update:model-value="emit('update:modelValue', $event)"
        />
    </div>
</template>
