<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * A free-text list of short strings — the personality traits and the allergies.
 *
 * Traits are free text rather than a fixed checklist because the backend rule
 * is `traits.*: nullable|string|max:255` with no vocabulary behind it. The
 * legacy form offered a hardcoded English checklist and posted trait *ids*,
 * which is a shape nothing on this side accepts.
 *
 * The list is emitted, never mutated in place, so the parent's `useForm` state
 * stays the only owner of it.
 */
const {
    modelValue,
    inputId,
    placeholder = 'Add one and press Enter',
} = defineProps<{
    modelValue: string[];
    inputId: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const draft = ref('');

function add(): void {
    const value = draft.value.trim();

    if (value === '' || modelValue.includes(value)) {
        draft.value = '';

        return;
    }

    emit('update:modelValue', [...modelValue, value]);
    draft.value = '';
}

function remove(value: string): void {
    emit(
        'update:modelValue',
        modelValue.filter((entry) => entry !== value),
    );
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex gap-2">
            <Input
                :id="inputId"
                v-model="draft"
                :placeholder="placeholder"
                @keydown.enter.prevent="add"
            />
            <Button type="button" variant="outline" size="icon" @click="add">
                <Plus class="size-4" />
                <span class="sr-only">Add</span>
            </Button>
        </div>

        <div v-if="modelValue.length > 0" class="flex flex-wrap gap-2">
            <Badge
                v-for="value in modelValue"
                :key="value"
                variant="secondary"
                class="gap-1"
            >
                {{ value }}
                <button
                    type="button"
                    class="hover:text-destructive"
                    :aria-label="`Remove ${value}`"
                    @click="remove(value)"
                >
                    <X class="size-3" />
                </button>
            </Badge>
        </div>
    </div>
</template>
