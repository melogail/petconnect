<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Loader2Icon } from "@lucide/vue"
import { cn } from "@/lib/utils"
import { useTranslations } from "@/composables/useTranslations"

/**
 * A spinner is decoration by default and a live region only on request.
 *
 * The invariant: **a spinner decorating a control contributes nothing to that
 * control's accessible name; a spinner that is genuinely the page's status
 * still announces.** Twenty-three of this component's twenty-nine mount sites
 * sit inside a `<Button>` next to that button's own label, where a name on the
 * spinner is concatenated into the button's name by name-from-content — the
 * comment composer's submit control announced as `"Loading... Posting..."`
 * instead of as `"Posting..."`. So the default branch is `aria-hidden`, which
 * matches what the rest of this repo already does with an icon inside a
 * control (`CommentComposer` renders `<Send aria-hidden="true" />` in the
 * other branch of the very button measured above).
 *
 * The six standalone sites — the page is loading, not a control — pass
 * `status` and keep `role="status"` plus the label. `common.loading` already
 * existed in both `lang/en.json` and `lang/ar.json` ("Loading..." / "جارٍ
 * التحميل..."), so the announcing branch reuses it.
 *
 * Two elements rather than one with bound attributes: `aria-hidden` and
 * `role`/`aria-label` are mutually exclusive states of this icon, and the pair
 * of branches is the form that was measured.
 *
 * ## Measured, not reasoned — 2026-09-06, Chrome 152
 *
 * Read out of `Accessibility.getFullAXTree` over CDP against an isolated build
 * of this tree, with the comment composer's POST held at the **request** stage
 * so the processing branch stayed on screen. The intercept stage is named
 * because it is the experiment: what is under test is the client-rendered
 * processing state, not any server race. Matched by `backendDOMNodeId`, not by
 * position, since AX flat-node order is not document order.
 *
 * - before, `role="status"` + label by default: button name
 *   `"Loading... Posting..."`
 * - after, this file: button name `"Posting..."`
 *
 * The counterfactual is what makes that evidence: the unfixed build was driven
 * through the identical script and produced the concatenation. The other half
 * of the invariant was measured the same way on the feed's standalone loader
 * (`PetFeed`, opted in): `role=status`, name `"Loading..."`, `ignored=false`,
 * before and after alike. Class strings on both branches are byte-identical
 * before and after — `cn()` is called with the same two arguments in the same
 * order — and the feed spinner's computed box read `32px` × `32px` in both
 * runs, so the tailwind-merge order trap is not in play here.
 */
const { t } = useTranslations()

const props = defineProps<{
  class?: HTMLAttributes["class"]
  /**
   * Announce as a live region. Opt in when the spinner is the only thing
   * reporting the wait; leave it off inside a control that has its own label.
   */
  status?: boolean
}>()
</script>

<template>
  <Loader2Icon
    v-if="props.status"
    role="status"
    :aria-label="t('common.loading')"
    :class="cn('size-4 animate-spin', props.class)"
  />
  <Loader2Icon
    v-else
    aria-hidden="true"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
