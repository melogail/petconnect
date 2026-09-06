<script setup lang="ts">
import type { PopoverContentEmits, PopoverContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { PopoverContent, PopoverPortal, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

/**
 * Two deliberate trims against the upstream shadcn-vue base class, both so that
 * a caller does not have to undo them:
 *
 * - No `w-72 border p-4 shadow-md`. The surface geometry is the caller's, and
 *   `MessagesDropdown` is a full-bleed panel — its own header strip, scroll
 *   region and footer reach the edges — so it would otherwise have to pass
 *   `p-0 border-0` to cancel them.
 * - No `data-[side=*]:slide-in-from-*`. `cn()` merges a conflicting utility but
 *   cannot remove one, so a caller whose transition is fade-and-scale only —
 *   which is what the ported legacy panel is — could not opt out without
 *   spelling out four `slide-in-from-*-0` overrides.
 *
 * What is left is what every popover needs and none would override: the popover
 * surface tokens, the stacking context, the transform origin Reka publishes,
 * and the open/closed animation pair.
 */
defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(
  defineProps<PopoverContentProps & { class?: HTMLAttributes["class"] }>(),
  {
    sideOffset: 4,
  },
)
const emits = defineEmits<PopoverContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <PopoverPortal>
    <PopoverContent
      data-slot="popover-content"
      v-bind="{ ...$attrs, ...forwarded }"
      :class="cn('bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 z-50 origin-(--reka-popover-content-transform-origin) rounded-md outline-hidden', props.class)"
    >
      <slot />
    </PopoverContent>
  </PopoverPortal>
</template>
