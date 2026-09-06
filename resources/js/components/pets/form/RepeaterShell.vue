<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';

/**
 * The chrome around a repeater: rows, a remove button per row, an add button.
 *
 * The vaccination and medication repeaters differ only in the fields inside a
 * row, so the row template is a scoped slot and each caller keeps its own
 * typed fields. That is what stops a shared "generic repeater" from having to
 * take untyped `Record<string, string>` rows.
 */
defineProps<{
    rows: unknown[];
    addLabel: string;
    emptyLabel: string;
}>();

const emit = defineEmits<{
    add: [];
    remove: [index: number];
}>();
</script>

<template>
    <div class="space-y-3">
        <p v-if="rows.length === 0" class="text-muted-foreground text-sm">
            {{ emptyLabel }}
        </p>

        <div
            v-for="(row, index) in rows"
            :key="index"
            class="border-border flex items-end gap-2 rounded-lg border p-3"
        >
            <div class="grid flex-1 gap-3 sm:grid-cols-2">
                <slot name="row" :index="index" :row="row" />
            </div>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                :aria-label="`Remove row ${index + 1}`"
                @click="emit('remove', index)"
            >
                <X class="size-4" />
            </Button>
        </div>

        <Button type="button" variant="outline" size="sm" @click="emit('add')">
            <Plus class="size-4" />
            {{ addLabel }}
        </Button>
    </div>
</template>
