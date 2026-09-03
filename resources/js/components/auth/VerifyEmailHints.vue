<script setup lang="ts">
import { Inbox, ShieldCheck } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The two "what to do next" lines under the card's status area.
 *
 * The tile colours are spelled out per item rather than derived from a `tone`
 * prop, because Tailwind resolves class names at build time from the source
 * text: a `bg-${tone}-50` would compile to nothing at all and the tiles would
 * come out transparent. Two items with two literal palettes is also the whole
 * of the design — there is no third tone waiting to be parameterised.
 */
const { t } = useTranslations();
</script>

<template>
    <ul class="space-y-3">
        <li class="flex items-start gap-3 text-start">
            <div
                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-300"
            >
                <Inbox class="size-4" />
            </div>
            <p class="text-muted-foreground text-sm">
                {{ t('auth.verify_email_check_inbox') }}
            </p>
        </li>
        <li class="flex items-start gap-3 text-start">
            <div
                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-950/60 dark:text-fuchsia-300"
            >
                <ShieldCheck class="size-4" />
            </div>
            <p class="text-muted-foreground text-sm">
                {{ t('auth.verify_email_spam_hint') }}
            </p>
        </li>
    </ul>
</template>
