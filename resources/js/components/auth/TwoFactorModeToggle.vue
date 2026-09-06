<script setup lang="ts">
import { useTranslations } from '@/composables/useTranslations';

/**
 * "or you can <switch to the other credential>", under both two-factor forms.
 *
 * It is a component because the row is rendered twice — once under the
 * authenticator-code form and once under the recovery-code form — with nothing
 * but the label differing, and the two copies had already drifted in the
 * legacy page, which spelled the same six utility classes in two orders. One
 * definition is one thing to restyle.
 *
 * It emits rather than acts: which form is on screen is the page's state, and a
 * child that flipped it would be reaching upward through a prop.
 */
defineProps<{
    /** The other mode's name, already translated by the caller. */
    label: string;
}>();

defineEmits<{
    toggle: [];
}>();

const { t } = useTranslations();
</script>

<template>
    <div class="text-muted-foreground text-center text-sm">
        <span>{{ t('auth.or_you_can') }} </span>
        <button
            type="button"
            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
            @click="$emit('toggle')"
        >
            {{ label }}
        </button>
    </div>
</template>
