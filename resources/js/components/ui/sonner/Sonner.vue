<script lang="ts" setup>
import type { ToasterProps } from 'vue-sonner';
import { Toaster as Sonner } from 'vue-sonner';
import { CircleAlert, CircleCheck, Info, Loader2, TriangleAlert } from 'lucide-vue-next';

const props = defineProps<ToasterProps>();
</script>

<template>
    <Sonner
        class="toaster group"
        v-bind="props"
        position="top-right"
        :close-button="true"
        :expand="true"
        :visible-toasts="4"
        :toast-options="{
            unstyled: true,
            duration: 4000,
            classes: {
                toast: 'group flex w-[min(100vw-2rem,22rem)] items-start gap-3 rounded-xl border border-border/90 bg-background/95 px-4 py-3 text-foreground shadow-lg ring-1 ring-black/5 backdrop-blur-md dark:bg-card/95 dark:ring-white/10',
                title: 'text-sm font-semibold leading-tight tracking-tight text-foreground',
                description: 'text-sm leading-snug text-muted-foreground',
                success: '!border-l-[3px] !border-l-primary !pl-[calc(1rem-3px)]',
                error: '!border-l-[3px] !border-l-destructive !pl-[calc(1rem-3px)]',
                warning: '!border-l-[3px] !border-l-amber-500 !pl-[calc(1rem-3px)]',
                info: '!border-l-[3px] !border-l-blue-500 !pl-[calc(1rem-3px)]',
                closeButton:
                    'rounded-md p-1 text-muted-foreground opacity-80 transition hover:bg-muted hover:text-foreground hover:opacity-100',
                actionButton:
                    'rounded-lg bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90',
                cancelButton:
                    'rounded-lg border border-border bg-background px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted',
            },
        }"
    >
        <template #success-icon>
            <CircleCheck class="mt-0.5 size-[18px] shrink-0 text-primary" aria-hidden="true" />
        </template>
        <template #error-icon>
            <CircleAlert class="mt-0.5 size-[18px] shrink-0 text-destructive" aria-hidden="true" />
        </template>
        <template #info-icon>
            <Info class="mt-0.5 size-[18px] shrink-0 text-blue-500 dark:text-blue-400" aria-hidden="true" />
        </template>
        <template #warning-icon>
            <TriangleAlert class="mt-0.5 size-[18px] shrink-0 text-amber-500 dark:text-amber-400" aria-hidden="true" />
        </template>
        <template #loading-icon>
            <Loader2 class="mt-0.5 size-[18px] shrink-0 animate-spin text-primary" aria-hidden="true" />
        </template>
    </Sonner>
</template>
