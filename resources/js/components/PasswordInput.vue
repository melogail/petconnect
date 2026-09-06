<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

/**
 * A password field with a reveal toggle sitting inside its trailing edge.
 *
 * Every class that positions the toggle is logical, not physical — `pe-10` on
 * the field, `end-0` and `rounded-e-md` on the button — because ten call sites
 * mount it (four auth screens, the three fields on `settings/Security`, and
 * `DeleteUser`) and Arabic renders the whole document `rtl`.
 *
 * Measured, not assumed: the component SSR-rendered into a 320px-wide box that
 * inlines the stylesheet under the **`resources/css/app.css`** manifest key
 * (not the `resources/js/app.ts` one, which carries no Tailwind utilities),
 * screenshotted and read back in Chrome under `dir="ltr"` and `dir="rtl"`.
 * Stylesheet-live guard: body `margin` 0px, field `border-radius` 10px
 * (`rounded-md`), field `height` 36px (`h-9`). The box is pinned by CSS
 * (`width: 320px`), not by the viewport — `innerWidth` read 500px — and no
 * figure below depends on the viewport.
 *
 * With the physical classes this shipped with (`pr-10`, `right-0`,
 * `rounded-r-md`), `ltr` and `rtl` measured **identically** — button 40px wide
 * flush to the physical right edge, field `padding-right` 40px /
 * `padding-left` 12px, radius on the two right corners — which under `rtl`
 * puts the toggle on the **leading** edge, ahead of where the value starts,
 * and pads the leading edge to make room for it. The screenshot shows the dots
 * beginning 40px in from the right with the eye outboard of them: not an
 * overlap, but the mirror image of the design.
 *
 * With the logical classes the two directions mirror. `ltr` is byte-for-byte
 * what it was; `rtl` flips: gap from the inline end 280px → 0px, gap from the
 * inline start 0px → 280px, `padding-left` 12px → 40px, `padding-right` 40px →
 * 12px, and the 10px radii move from the two right corners to the two left
 * ones. Re-run before trusting these: they are one component in one box, not a
 * whole page.
 */
const { t } = useTranslations();

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const showPassword = ref(false);
const inputRef = useTemplateRef('inputRef');

defineExpose({
    $el: inputRef,
    focus: () => inputRef.value?.$el?.focus(),
});
</script>

<template>
    <div class="relative">
        <Input
            ref="inputRef"
            :type="showPassword ? 'text' : 'password'"
            :class="cn('pe-10', props.class)"
            v-bind="$attrs"
        />
        <button
            type="button"
            @click="showPassword = !showPassword"
            :class="
                cn(
                    'text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute inset-y-0 end-0 flex items-center rounded-e-md px-3 focus-visible:ring-[3px] focus-visible:outline-none',
                )
            "
            :aria-label="
                showPassword
                    ? t('common.hide_password')
                    : t('common.show_password')
            "
            :tabindex="-1"
        >
            <EyeOff v-if="showPassword" class="size-4" />
            <Eye v-else class="size-4" />
        </button>
    </div>
</template>
