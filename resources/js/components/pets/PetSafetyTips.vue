<script setup lang="ts">
import { CheckCircle2 } from '@lucide/vue';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The four safety tips under the owner panel in the sticky sidebar.
 *
 * Static copy, and legacy's four in legacy's order: meet in public, never send
 * money in advance, check the health records, ask for ownership documents. All
 * four keys were already in `lang/en.json` and `lang/ar.json` — they were
 * ported with the rest of the legacy catalogue and had no reader until now.
 *
 * A plain `ui/card`, not the `rounded-2xl` frame the owner panel and the header
 * use. That is legacy's own split: it builds those two by hand and reaches for
 * its `Card` component here, so the block reads as an aside rather than as a
 * third thing about this listing.
 */
const { t } = useTranslations();

const tips = computed(() => [
    t('pets.safety_tip_public_place'),
    t('pets.safety_tip_no_money'),
    t('pets.safety_tip_health_records'),
    t('pets.safety_tip_ownership_docs'),
]);
</script>

<template>
    <Card class="border-border/50">
        <CardHeader class="pb-3">
            <CardTitle class="text-base">{{ t('pets.safety_tips') }}</CardTitle>
        </CardHeader>
        <CardContent>
            <ul class="space-y-2.5">
                <li
                    v-for="tip in tips"
                    :key="tip"
                    class="flex items-start gap-2.5"
                >
                    <CheckCircle2
                        class="text-primary mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="text-sm">{{ tip }}</span>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
