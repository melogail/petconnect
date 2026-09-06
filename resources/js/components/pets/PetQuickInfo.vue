<script setup lang="ts">
import { CheckCircle2, Palette, Scale, XCircle } from '@lucide/vue';
import { computed, type Component } from 'vue';
import PetSectionHeading from '@/components/pets/PetSectionHeading.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { PetDetail } from '@/types';

/**
 * The "Quick Info" tile grid — legacy's `PetStats`.
 *
 * Four facts at most, and the two that are always present lead: vaccination
 * and neutering are booleans, so they render either way, while weight and
 * colour are nullable columns and drop out when empty. `highlight` paints the
 * icon in brand violet for a fact that reads as a positive; legacy used the
 * same flag and used it for exactly this.
 *
 * ## Three of legacy's tiles are deliberately gone
 *
 * "With Kids", "With Other Pets" and "Size" were pulled out of
 * `additional_info` by string-matching its keys against the English labels
 * `Good with Kids`, `Good with Other Pets` and `Size`. `additional_info` is a
 * free-form map the owner types, so those three tiles were blank for every
 * listing written in Arabic and for every owner who spelled the key any other
 * way. That is a legacy defect, not a feature: the same entries still render,
 * under whatever labels the owner actually used, in `PetExtras` further down
 * the same card.
 *
 * The heading is `PetSectionHeading`, the same component the other in-card
 * blocks use, rather than a second copy of its classes. Two things are passed
 * in rather than hardcoded:
 *
 * - `level="h2"`, because this is a top-level section of the page. It used to
 *   be justified here as "not nested inside the facts card's `h2`" — there is
 *   no such `h2`, `PetFactsCard` renders no heading at all, and the blocks
 *   that took the `h3` default on the strength of that sentence are `h2` too
 *   now. The value passed here did not change; the reason did.
 * - `class="mb-4"`, because legacy gives this grid `mb-4` where the in-card
 *   blocks get `mb-3` (`PetStats.vue:102` against `PetAbout.vue:28` in
 *   petconnect-old). See `PetSectionHeading` for why that 4px is passed
 *   through instead of being averaged away.
 *
 * The neutering tile's label is `wizard.spayed_neutered` — a key from the pet
 * form's namespace, read here on a public page. That reuse was argued against
 * before it shipped, and renaming the namespace would render the raw key to
 * visitors; the decision and what it costs are recorded once, in
 * `PetHealthSection`'s docblock. Pointer, not a copy.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const { t } = useTranslations();

type Tile = {
    key: string;
    icon: Component;
    label: string;
    value: string;
    highlight: boolean;
};

/**
 * Legacy drops the weight tile on a **falsy** weight, not on a null one —
 * `...(props.petDetails.weight ? [...] : [])` in
 * `components/pet/show/PetStats.vue:44` — so a listing weighing 0 renders no
 * tile there. Ported as `weight !== null`, which renders a "0 kg" tile legacy
 * never showed.
 *
 * The port back is not the one character it looks like. `pets.weight` is an
 * uncast `decimal` (`DecimalColumn`): a float on SQLite and a **string** on
 * MySQL, per `@/types/profile`, and `Boolean('0.00')` is `true` — so a bare
 * truthiness check reproduces legacy's behaviour on the test driver and not on
 * the dev/production one. `Number()` is the coercion this codebase already
 * applies at every other `DecimalColumn` boundary, and `Number(null)` is `0`,
 * so the null case falls out of the same test.
 *
 * Established by reading both trees, not by rendering either.
 */
const weight = computed(() =>
    pet.weight !== null && Number(pet.weight) > 0 ? pet.weight : null,
);

const tiles = computed<Tile[]>(() => {
    const items: Tile[] = [
        {
            key: 'vaccination',
            icon: pet.health.vaccinated ? CheckCircle2 : XCircle,
            label: t('pets.vaccination'),
            value: pet.health.vaccinated
                ? t('pets.up_to_date')
                : t('pets.needed'),
            highlight: pet.health.vaccinated,
        },
        {
            key: 'spayed',
            icon: pet.health.spayedNeutered ? CheckCircle2 : XCircle,
            label: t('wizard.spayed_neutered'),
            value: pet.health.spayedNeutered ? t('common.yes') : t('common.no'),
            highlight: pet.health.spayedNeutered,
        },
    ];

    if (weight.value !== null) {
        items.push({
            key: 'weight',
            icon: Scale,
            label: t('pets.weight'),
            value: t('pets.weight_kg', { value: weight.value }),
            highlight: true,
        });
    }

    if (pet.color) {
        items.push({
            key: 'color',
            icon: Palette,
            label: t('pets.color'),
            value: pet.color,
            highlight: true,
        });
    }

    return items;
});
</script>

<template>
    <section class="mb-6">
        <PetSectionHeading
            :title="t('pets.quick_info')"
            level="h2"
            class="mb-4"
        />
        <div
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3"
        >
            <div
                v-for="tile in tiles"
                :key="tile.key"
                class="border-border/50 bg-card group hover:border-primary/30 relative overflow-hidden rounded-xl border p-4 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
            >
                <div
                    class="from-primary/5 absolute inset-0 rounded-xl bg-gradient-to-br to-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                />
                <component
                    :is="tile.icon"
                    class="relative mb-2 size-5 transition-colors"
                    :class="
                        tile.highlight
                            ? 'text-primary'
                            : 'text-muted-foreground'
                    "
                    aria-hidden="true"
                />
                <p class="text-muted-foreground relative mb-0.5 text-xs">
                    {{ tile.label }}
                </p>
                <p class="text-foreground relative text-sm font-semibold">
                    {{ tile.value }}
                </p>
            </div>
        </div>
    </section>
</template>
