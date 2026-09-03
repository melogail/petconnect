<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Loader2Icon } from "@lucide/vue"
import { cn } from "@/lib/utils"
import { useTranslations } from "@/composables/useTranslations"

/**
 * The `aria-label` is the only string this renders, and it is the one a screen
 * reader hears while a form is submitting, so it goes through the catalogue
 * like every other visible string. `common.loading` already existed in both
 * `lang/en.json` and `lang/ar.json` ("Loading..." / "جارٍ التحميل..."), so
 * this reuses it rather than adding a near-duplicate key.
 */
const { t } = useTranslations()

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <Loader2Icon
    role="status"
    :aria-label="t('common.loading')"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
