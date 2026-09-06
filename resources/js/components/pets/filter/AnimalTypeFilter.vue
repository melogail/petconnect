<script setup lang="ts">
import { reactive, watch } from 'vue';
import CategoryFilterRow from '@/components/pets/filter/CategoryFilterRow.vue';
import {
    type CategorySelection,
    setCategoryBreeds,
    toggleCategory,
} from '@/components/pets/filter/selection';
import { useTranslations } from '@/composables/useTranslations';
import type { PetCategoryOption } from '@/types';

/**
 * The category → breed tree.
 *
 * It owns exactly one thing the sheet does not: which panels are open. That is
 * not part of the filter, it never reaches the query string, and it is
 * discarded when the sheet closes — reka unmounts the sheet's content, so this
 * component remounts on every open and the watcher below re-derives the
 * opening state from whatever came back out of the URL.
 *
 * The rule is "a category expands the moment it acquires a selection it did not
 * have", and it covers both cases the legacy filter handled with two separate
 * pieces of code: hydrating from the address bar (`immediate`, `previous`
 * undefined, so every category with a selection opens) and the parent checkbox
 * selecting a whole category (which legacy expanded by hand). Selecting a
 * breed inside an already-open panel changes nothing, and nothing here ever
 * collapses a panel the visitor opened.
 *
 * This works because the selection map is replaced, never mutated: every helper
 * in `selection.ts` returns a new object, so the watcher sees an identity change
 * per edit and gets a usable `previous`.
 */
const { categories } = defineProps<{ categories: PetCategoryOption[] }>();

const selection = defineModel<CategorySelection>({ required: true });

const { t } = useTranslations();

const expanded = reactive<Record<number, boolean>>({});

watch(
    selection,
    (next, previous) => {
        for (const key of Object.keys(next)) {
            const categoryId = Number(key);

            if (!previous?.[categoryId]) {
                expanded[categoryId] = true;
            }
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-2">
        <!--
            `<Deferred>`'s fallback covers `undefined`, which is the prop still
            in flight. This covers the other empty: the request landed and the
            taxonomy is genuinely empty, which otherwise leaves a bare gap under
            the "Animal Type" heading.
        -->
        <p v-if="categories.length === 0" class="text-muted-foreground text-xs">
            {{ t('home.no_animal_types') }}
        </p>

        <CategoryFilterRow
            v-for="category in categories"
            :key="category.id"
            v-model:expanded="expanded[category.id]"
            :category="category"
            :selected-breed-ids="selection[category.id] ?? []"
            @toggle="selection = toggleCategory(selection, category)"
            @update:selected-breed-ids="
                selection = setCategoryBreeds(selection, category.id, $event)
            "
        />
    </div>
</template>
