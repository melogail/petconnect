<script setup lang="ts">
import { Check, ChevronRight, Minus } from '@lucide/vue';
import { computed } from 'vue';
import BreedFilterList from '@/components/pets/filter/BreedFilterList.vue';
import { categoryIcon } from '@/components/pets/filter/categoryIcon';
import { categoryState } from '@/components/pets/filter/selection';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { taxonomyName } from '@/lib/taxonomy';
import type { PetCategoryOption } from '@/types';

/**
 * One category in the animal-type tree: a disclosure arrow, a tri-state
 * checkbox, the category's icon and name, a `selected/total` badge, and the
 * breed list it opens onto.
 *
 * ## The tri-state checkbox
 *
 * `ui/checkbox` is reka's `CheckboxRoot`, which already models the third state
 * — `model-value="indeterminate"` renders `aria-checked="mixed"` and
 * `data-state="indeterminate"`. That is why this reuses it instead of the
 * legacy filter's hand-styled `<input type="checkbox">` with
 * `:indeterminate.prop`: the native property is invisible to assistive
 * technology unless it is mirrored into `aria-checked`, which the legacy markup
 * never did, and the legacy's own `:focus` ring was
 * `hsl(var(--primary) / 0.2)` — Tailwind 3 token syntax that resolves to
 * nothing here, where `--primary` is already a complete `hsl(...)`.
 *
 * Two things follow from reusing it. The indeterminate colours are not in the
 * shared component (it styles `data-[state=checked]` only), so they are passed
 * in as a class. And the emitted value is **ignored**: reka moves indeterminate
 * to checked on click, whereas a tree's parent checkbox must clear the whole
 * category from either lit state. `@update:model-value` is therefore used only
 * as "the visitor clicked it", and `toggle` carries the real semantics.
 *
 * A category with no breeds cannot be selected at all — the query it would
 * build is "all of no breeds" — so the control is disabled rather than left
 * looking live and doing nothing, which is what the legacy did. That a category
 * *without* breeds is unfilterable at all is a limit of the two-key query, not
 * of this control; it is unreachable with the seeded taxonomy, where all seven
 * categories have breeds.
 *
 * Measured in headless Chrome, driving this row with three breeds: one breed
 * picked reports `aria-checked="mixed"` and `data-state="indeterminate"`, draws
 * `lucide-minus` rather than `lucide-check`, and computes to
 * `rgb(124, 58, 237)` — `--primary` in the light theme — so the indeterminate
 * class does land. Clicking it there **cleared** the category (`aria-checked`
 * `false`, badge gone) rather than completing it, and clicking again selected
 * all three and left the panel open.
 *
 * ## The arrow under RTL
 *
 * Collapsed it points along the reading direction, so it needs `rtl:rotate-180`
 * as well as the open state's `rotate-90`; a bare `rotate-90` toggle points the
 * closed arrow *away* from the panel it opens in Arabic. Measured: `rotate` of
 * `none` / `90deg` collapsed and expanded under `dir="ltr"`, `180deg` / `90deg`
 * under `dir="rtl"`.
 *
 * Read `rotate`, not `transform`, if you ever re-check that. Tailwind v4's
 * `rotate-*` emit the individual `rotate` property, so
 * `getComputedStyle(icon).transform` is `"none"` in every one of those four
 * states — a first pass of this measurement read `transform` and reported a
 * class that never applies, which is not what is happening.
 */
const { category, selectedBreedIds } = defineProps<{
    category: PetCategoryOption;
    /** The ids picked inside this category, from the shared selection map. */
    selectedBreedIds: number[];
}>();

const expanded = defineModel<boolean>('expanded', { default: false });

const emit = defineEmits<{
    /** The visitor hit the parent checkbox; the tree decides what that means. */
    toggle: [];
    'update:selectedBreedIds': [ids: number[]];
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const checkboxId = computed(() => `filter-category-${category.id}`);

const label = computed(() => taxonomyName(category, locale.value.current));

const breeds = computed(() => category.breeds ?? []);

/**
 * A breedless category's checkbox is disabled, so its label must not advertise
 * a click that does nothing — the whole row is a label for that control.
 */
const isDisabled = computed(() => breeds.value.length === 0);

const state = computed(() => categoryState(selectedBreedIds, category));
</script>

<template>
    <Collapsible v-model:open="expanded" class="rounded-xl">
        <div
            class="hover:bg-muted/50 flex items-center gap-2 rounded-xl p-2 transition-colors"
        >
            <CollapsibleTrigger as-child>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/50 flex size-8 shrink-0 items-center justify-center rounded-md focus-visible:ring-[3px] focus-visible:outline-none"
                    :aria-label="
                        expanded
                            ? t('home.collapse_breeds')
                            : t('home.expand_breeds')
                    "
                >
                    <ChevronRight
                        class="size-4 transition-transform duration-200"
                        :class="expanded ? 'rotate-90' : 'rtl:rotate-180'"
                    />
                </button>
            </CollapsibleTrigger>

            <Checkbox
                :id="checkboxId"
                v-slot="{ state: checkboxState }"
                :model-value="state"
                :disabled="isDisabled"
                class="data-[state=indeterminate]:bg-primary data-[state=indeterminate]:border-primary data-[state=indeterminate]:text-primary-foreground"
                @update:model-value="emit('toggle')"
            >
                <Minus
                    v-if="checkboxState === 'indeterminate'"
                    class="size-3.5"
                />
                <Check v-else class="size-3.5" />
            </Checkbox>

            <Label
                :for="checkboxId"
                class="min-w-0 flex-1 gap-3 py-1 pe-1"
                :class="isDisabled ? 'cursor-default' : 'cursor-pointer'"
            >
                <component
                    :is="categoryIcon(category.slug)"
                    class="size-4 shrink-0"
                />
                <span class="truncate">{{ label }}</span>
                <Badge
                    v-if="selectedBreedIds.length > 0"
                    variant="secondary"
                    class="ms-auto"
                >
                    {{ selectedBreedIds.length }}/{{ breeds.length }}
                </Badge>
            </Label>
        </div>

        <CollapsibleContent class="overflow-hidden">
            <BreedFilterList
                v-if="breeds.length > 0"
                :breeds="breeds"
                :model-value="selectedBreedIds"
                :id-prefix="`filter-breed-${category.id}`"
                @update:model-value="emit('update:selectedBreedIds', $event)"
            />
            <p v-else class="text-muted-foreground ms-10 py-2 ps-3 text-xs">
                {{ t('home.no_breeds') }}
            </p>
        </CollapsibleContent>
    </Collapsible>
</template>
