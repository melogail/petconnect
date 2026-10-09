<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The light/dark knob in the header.
 *
 * `useAppearance()` is tri-state (`light | dark | system`) and stays that way;
 * this is the two-position view of it. It reads `resolvedAppearance`, so a
 * reader on `system` sees the knob where their OS has actually put them, and
 * the first click resolves them to an explicit choice in that direction.
 * `AppearanceTabs` on the settings screen is where `system` is chosen back.
 *
 * The knob travels with `translate-x` and is mirrored by `rtl:`, because a
 * transform is physical: without it the knob would slide off the pill under
 * `dir="rtl"`.
 */
const { resolvedAppearance, updateAppearance } = useAppearance();

const { t } = useTranslations();

const isDark = computed(() => resolvedAppearance.value === 'dark');

const label = computed(() =>
    isDark.value ? t('nav.switch_to_light_mode') : t('nav.switch_to_dark_mode'),
);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="isDark"
        :aria-label="label"
        :title="label"
        class="focus-visible:ring-ring focus-visible:ring-offset-background relative inline-flex h-8 w-14 shrink-0 items-center rounded-full transition-[background-color,border-color,box-shadow,transform] duration-200 ease-out outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
        :class="isDark ? 'bg-primary' : 'bg-muted'"
        @click="updateAppearance(isDark ? 'light' : 'dark')"
    >
        <span
            class="bg-card shadow-card flex size-6 items-center justify-center rounded-full transition-transform duration-200 ease-out"
            :class="
                isDark
                    ? 'translate-x-7 rtl:-translate-x-7'
                    : 'translate-x-1 rtl:-translate-x-1'
            "
        >
            <Sun v-if="!isDark" class="text-foreground size-4" />
            <Moon v-else class="text-primary size-4" />
        </span>
    </button>
</template>
