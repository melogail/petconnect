<script setup lang="ts">
import { Inbox, ShieldCheck } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The two "what to do next" lines under the card's status area.
 *
 * Both tiles are the same `bg-secondary text-secondary-foreground` disc. They
 * used to be two literal palettes — violet for the inbox, fuchsia for the spam
 * hint — and the "Aubergine & Lavender" restyle collapsed them to one: violet
 * is the only accent, so there is no second tone to give the second tile. The
 * classes are still written out per item rather than derived from a `tone`
 * prop, because Tailwind resolves class names at build time from the source
 * text: a `bg-${tone}` would compile to nothing at all and the tiles would come
 * out transparent — and with one palette there is nothing to parameterise.
 *
 * The hint copy stays `text-muted-foreground` because it sits on the card
 * (`#756B88` on `#FFFFFF` 4.98:1), not on the lavender panel, where the same
 * token would fall to 4.22:1.
 */
const { t } = useTranslations();
</script>

<template>
    <ul class="space-y-3">
        <li class="flex items-start gap-3 text-start">
            <div
                class="bg-secondary text-secondary-foreground mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl"
            >
                <Inbox class="size-4" />
            </div>
            <p class="text-muted-foreground text-sm">
                {{ t('auth.verify_email_check_inbox') }}
            </p>
        </li>
        <li class="flex items-start gap-3 text-start">
            <div
                class="bg-secondary text-secondary-foreground mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl"
            >
                <ShieldCheck class="size-4" />
            </div>
            <p class="text-muted-foreground text-sm">
                {{ t('auth.verify_email_spam_hint') }}
            </p>
        </li>
    </ul>
</template>
