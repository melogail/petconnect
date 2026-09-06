<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * "Verification email sent to <address>", so the reader can tell at a glance
 * whether the message is going somewhere they can reach.
 *
 * The address is the one string on this screen that is neither translated nor
 * bounded, so it gets `truncate` and the row gets `min-w-0` — a flex child
 * defaults to `min-width: auto` and would otherwise push the chip wider than
 * the card rather than clipping.
 */
defineProps<{
    email: string;
}>();

const { t } = useTranslations();
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-xl border border-violet-100 bg-violet-50/70 px-4 py-3 dark:border-violet-900/60 dark:bg-violet-950/40"
    >
        <div
            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-900/70 dark:text-violet-300"
        >
            <Inbox class="size-5" />
        </div>
        <div class="min-w-0 text-start">
            <p class="text-xs font-medium text-violet-700 dark:text-violet-300">
                {{ t('auth.verify_email_sent_to') }}
            </p>
            <p class="text-foreground truncate text-sm font-semibold">
                {{ email }}
            </p>
        </div>
    </div>
</template>
