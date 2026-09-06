<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { update as updateLocale } from '@/routes/locale';

/**
 * Language and appearance as menu items, for the widths where the header has
 * no room for the pill and the knob.
 *
 * Below `sm` the control cluster has to fit `Log in` + `Sign up` inside a
 * 320px viewport, so `LocaleSwitcher` and `AppearanceToggle` are hidden there
 * and this is what replaces them — in `ShellUserMenu` for a signed-in reader
 * and in `ShellPreferenceMenu` for a guest. Both call sites gate it on
 * `sm:hidden`; nothing is ever offered twice at the same width.
 *
 * The locale rows are plain `DropdownMenuItem`s and not `DropdownMenuRadioItem`
 * on purpose: the radio primitive pins its indicator with `absolute left-2` and
 * pads with `pl-8 pr-2`, all physical, so under `dir="rtl"` the tick lands on
 * the far side of the label. A leading `Check` inside a symmetric item mirrors
 * correctly and needs nothing overridden.
 */
const { locale } = useLocale();

const { resolvedAppearance, updateAppearance } = useAppearance();

const { t } = useTranslations();

const isDark = computed(() => resolvedAppearance.value === 'dark');

const appearanceLabel = computed(() =>
    isDark.value ? t('nav.switch_to_light_mode') : t('nav.switch_to_dark_mode'),
);

const options = computed(() =>
    locale.value.supported.map((code) => ({
        code,
        label: t(`locales.${code}`),
        isCurrent: code === locale.value.current,
    })),
);

function selectLocale(code: string): void {
    if (code === locale.value.current) {
        return;
    }

    router.post(updateLocale.url(), { locale: code }, { preserveScroll: true });
}
</script>

<template>
    <DropdownMenuGroup>
        <DropdownMenuLabel class="text-muted-foreground text-xs font-normal">
            {{ t('nav.language') }}
        </DropdownMenuLabel>

        <DropdownMenuItem
            v-for="option in options"
            :key="option.code"
            :lang="option.code"
            :aria-current="option.isCurrent ? 'true' : undefined"
            class="cursor-pointer"
            @select="selectLocale(option.code)"
        >
            <Check v-if="option.isCurrent" class="size-4" />
            <span v-else class="size-4" aria-hidden="true" />
            {{ option.label }}
        </DropdownMenuItem>

        <DropdownMenuSeparator />

        <DropdownMenuItem
            class="cursor-pointer"
            @select="updateAppearance(isDark ? 'light' : 'dark')"
        >
            <Sun v-if="isDark" class="size-4" />
            <Moon v-else class="size-4" />
            {{ appearanceLabel }}
        </DropdownMenuItem>
    </DropdownMenuGroup>
</template>
