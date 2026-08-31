<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * A rating, read only.
 *
 * `max` is `petconnect.reviews.max_rate`, which now reaches the page as the
 * `reviewBounds` prop — built from the same accessor the `max:` rule is built
 * from. The default is a floor for a caller that has no bounds to hand, not a
 * licence to keep hardcoding five: pass it down.
 */
const {
    rate,
    max = 5,
    class: className,
} = defineProps<{
    rate: number | null;
    max?: number;
    class?: string;
}>();

const stars = computed(() =>
    Array.from({ length: max }, (_, index) => index + 1),
);
const rounded = computed(() => Math.round(rate ?? 0));
</script>

<template>
    <div
        :class="cn('flex items-center gap-0.5', className)"
        role="img"
        :aria-label="rate === null ? 'Not rated' : `${rate} out of ${max}`"
    >
        <Star
            v-for="star in stars"
            :key="star"
            class="size-4"
            :class="
                star <= rounded
                    ? 'fill-amber-400 text-amber-400'
                    : 'text-muted-foreground/40'
            "
            aria-hidden="true"
        />
    </div>
</template>
