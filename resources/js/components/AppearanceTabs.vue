<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div class="bg-muted inline-flex gap-1 rounded-[14px] p-1">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'focus-visible:ring-ring focus-visible:ring-offset-background flex items-center rounded-[10px] px-3.5 py-1.5 transition-[background-color,border-color,box-shadow,transform] duration-200 ease-out outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
                appearance === value
                    ? 'bg-card text-foreground shadow-card'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
