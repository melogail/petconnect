<script setup lang="ts">
import { Shield } from '@lucide/vue';
import { computed } from 'vue';
import PetSectionHeading from '@/components/pets/PetSectionHeading.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { PetDetail } from '@/types';

/**
 * "Meet Luna!" — the description and the personality traits.
 *
 * Legacy greets with the first word of the name (`name.split(' ')[0]`), which
 * is what a two-word listing name like "Luna Belle" is meant to shorten to;
 * the whole string is used when there is no space in it.
 *
 * An empty description falls back to `pets.no_description` rather than
 * collapsing the block, because legacy always renders the paragraph and the
 * greeting reads oddly followed by nothing.
 *
 * The traits are lavender-wash pills (`bg-secondary text-secondary-foreground`,
 * the guide's tinted surface), not `Badge`s: legacy drew them as violet
 * outline pills, and the pill shape is kept while the paint moves onto the
 * tokens. They are hand-written rather than `Badge variant="secondary"` so a
 * trait keeps its own padding and size and does not read as one of the
 * taxonomy badges in the header.
 *
 * The greeting is the guide's section-heading scale (§3.2) — Newsreader
 * 26/1.15 at weight 500 — and the description is 15px body in
 * `--soft-foreground`, the reading step one below `--foreground`.
 *
 * The greeting is this section's own `h2`; the traits heading below it takes
 * `PetSectionHeading`'s `h3` default because it genuinely is nested inside that
 * `h2`. It is one of only two `PetSectionHeading`s on the page still on the
 * default — the other is "Healthcare Details" — and the four that pass
 * `level="h2"` are named in that component's docblock.
 *
 * That traits heading reads `wizard.personality_traits` — a key from the pet
 * form's namespace, read here on a public page. The reuse was argued against
 * before it shipped, and renaming the namespace would render the raw key to
 * visitors; the decision and what it costs are recorded once, in
 * `PetHealthSection`'s docblock. Pointer, not a copy.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const { t } = useTranslations();

const firstName = computed(() => pet.name.split(' ')[0]);

const traits = computed(() => pet.traits ?? []);
</script>

<template>
    <section class="mb-6">
        <h2
            class="text-foreground mb-4 font-serif text-[26px] leading-[1.15] font-medium tracking-[-0.01em] text-balance"
        >
            {{ t('pets.meet_name', { name: firstName }) }}
        </h2>

        <p
            class="text-soft-foreground mb-6 text-[15px] leading-[1.55] text-pretty whitespace-pre-line"
        >
            {{ pet.description || t('pets.no_description') }}
        </p>

        <div v-if="traits.length > 0">
            <PetSectionHeading
                :title="t('wizard.personality_traits')"
                :icon="Shield"
            />
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="trait in traits"
                    :key="trait"
                    class="bg-secondary text-secondary-foreground hover:bg-primary-200 dark:hover:bg-primary-800 rounded-full px-3.5 py-1.5 text-[13px] font-medium transition-colors duration-200 ease-out"
                >
                    {{ trait }}
                </span>
            </div>
        </div>
    </section>
</template>
