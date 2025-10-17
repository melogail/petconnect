<script setup lang="ts">
import { cn } from '@/lib/utils';

defineOptions({
  name: 'Textarea',
  inheritAttrs: false,
});

const props = defineProps<{
  modelValue?: string | number;
  disabled?: boolean;
  class?: string;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number): void;
}>();

const handleInput = (event: Event) => {
  const target = event.target as HTMLTextAreaElement;
  emit('update:modelValue', target.value);
};
</script>

<template>
  <textarea
    :class="
      cn(
        'flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
        'placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2',
        'focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
        props.class
      )
    "
    :disabled="disabled"
    :value="modelValue"
    v-bind="$attrs"
    @input="handleInput"
  />
</template>
