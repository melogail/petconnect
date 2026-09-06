<script setup lang="ts">
import { computed } from 'vue';
import DetailList from '@/components/DetailList.vue';
import type { DetailItem } from '@/components/DetailList.vue';
import PetSectionHeading from '@/components/pets/PetSectionHeading.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { PetDetail } from '@/types';

/**
 * The owner's free-form extras — legacy's "Additional Information" block.
 *
 * `additionalInfo` is a **keyed map**, so it renders under whatever labels the
 * owner typed; nothing here matches a key against an English string. Legacy's
 * heading carries no icon, unlike the other four inside the facts card, which
 * is why `PetSectionHeading`'s `icon` is optional.
 *
 * `DetailList` is reused for the pairs rather than a second `<dl>` written
 * here: it already drops a row whose value is blank, which is exactly legacy's
 * `.filter(i => i?.key && i?.value)`.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const { t } = useTranslations();

const items = computed<DetailItem[]>(() =>
    Object.entries(pet.additionalInfo ?? {}).map(([label, value]) => ({
        label,
        value,
    })),
);
</script>

<template>
    <section v-if="items.length > 0">
        <PetSectionHeading
            :title="t('pets.additional_information')"
            level="h2"
        />
        <div class="border-border/50 bg-muted/10 rounded-xl border p-4">
            <DetailList :items="items" />
        </div>
    </section>
</template>
