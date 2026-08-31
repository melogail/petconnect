<script setup lang="ts">
import { computed } from 'vue';

/** One label/value pair. A null or blank value is not rendered at all. */
export type DetailItem = {
    label: string;
    value: string | null | undefined;
};

/**
 * A definition list of label/value pairs.
 *
 * Every fact panel on a listing is one of these — attributes, health, location,
 * the owner's free-form extras — so the "skip the empty ones" rule lives here
 * once instead of as a `v-if` per row.
 */
const { items } = defineProps<{ items: DetailItem[] }>();

const rows = computed(() =>
    items.filter(
        (item) =>
            item.value !== null &&
            item.value !== undefined &&
            item.value !== '',
    ),
);
</script>

<template>
    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
        <div v-for="row in rows" :key="row.label" class="min-w-0">
            <dt class="text-muted-foreground text-xs">{{ row.label }}</dt>
            <dd class="text-sm break-words">{{ row.value }}</dd>
        </div>
    </dl>
</template>
