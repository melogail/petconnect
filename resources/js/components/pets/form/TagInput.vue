<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';

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
    placeholder = undefined,
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

const { t } = useTranslations();
</script>

<template>
    <div class="space-y-2">
        <div class="flex gap-2">
            <Input
                :id="inputId"
                v-model="draft"
                :placeholder="placeholder ?? t('wizard.tag_placeholder')"
                @keydown.enter.prevent="add"
            />
            <Button type="button" variant="outline" size="icon" @click="add">
                <Plus class="size-4" />
                <span class="sr-only">{{ t('wizard.add') }}</span>
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
                    :aria-label="t('wizard.remove_named', { name: value })"
                    @click="remove(value)"
                >
                    <X class="size-3" />
                </button>
            </Badge>
        </div>
    </div>
</template>
