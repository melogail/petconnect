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
 * The traits are violet outline pills, not `Badge`s: legacy's own treatment,
 * and `Badge`'s `secondary` variant is the grey one the taxonomy badges in the
 * header use, so reusing it here would make a trait look like a breed.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const { t } = useTranslations();

const firstName = computed(() => pet.name.split(' ')[0]);

const traits = computed(() => pet.traits ?? []);
</script>

<template>
    <section class="mb-6">
        <h2 class="text-foreground mb-4 text-xl font-bold">
            {{ t('pets.meet_name', { name: firstName }) }}
        </h2>

        <p
            class="text-muted-foreground mb-6 leading-relaxed whitespace-pre-line"
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
                    class="border-primary/20 bg-primary/5 text-primary hover:bg-primary/10 rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors"
                >
                    {{ trait }}
                </span>
            </div>
        </div>
    </section>
</template>
