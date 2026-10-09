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
 *
 * The chip is a `bg-secondary` panel with a hairline `border-border`. Its icon
 * disc is `bg-card text-secondary-foreground`, not `bg-primary`: the hero
 * directly above already carries this card's one solid violet, and a second
 * solid disc here would break the one-accent rule. White on the lavender wash
 * reads as a lift, and the violet glyph on it measures 8.25:1 light (`#4A32C4`
 * on `#FFFFFF`) and 10.58:1 dark (`#CDBDFF` on `#181329`). The label is
 * `text-secondary-foreground` on the panel — 6.99:1 light, 9.17:1 dark —
 * computed from the hex values in `resources/css/app.css`.
 */
defineProps<{
    email: string;
}>();

const { t } = useTranslations();
</script>

<template>
    <div
        class="border-border bg-secondary flex items-center gap-3 rounded-[14px] border px-4 py-3"
    >
        <div
            class="bg-card text-secondary-foreground flex size-10 shrink-0 items-center justify-center rounded-xl"
        >
            <Inbox class="size-5" />
        </div>
        <div class="min-w-0 text-start">
            <p class="text-secondary-foreground text-xs font-medium">
                {{ t('auth.verify_email_sent_to') }}
            </p>
            <p class="text-foreground truncate text-sm font-semibold">
                {{ email }}
            </p>
        </div>
    </div>
</template>
