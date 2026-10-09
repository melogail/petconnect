<script setup lang="ts">
import { useVModel } from "@vueuse/core"
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <textarea
    v-model="modelValue"
    data-slot="textarea"
    :class="cn(
      'placeholder:text-muted-foreground bg-input border-border field-sizing-content min-h-16 w-full rounded-[14px] border px-3.5 py-2.5 text-[15px] transition-[color,border-color,box-shadow] duration-200 ease-out outline-none disabled:cursor-not-allowed disabled:opacity-50',
      'focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-ring/30',
      'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
      props.class,
    )"
  />
</template>
