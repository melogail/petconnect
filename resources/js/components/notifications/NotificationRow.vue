<script setup lang="ts">
import { Bell, Flag, Heart, Mail, MessageSquare, Star } from '@lucide/vue';
import { computed, type Component } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatRelative } from '@/lib/datetime';
import type { InboxNotification } from '@/types';

/**
 * What one notification *looks* like — type glyph, sentence, timestamp, unread
 * dot.
 *
 * Purely presentational, and deliberately separate from `NotificationItem`:
 * a row with a deep link is wrapped in a `<Link>` and a row without one is
 * wrapped in a `<div>`, and without this split that whole body would be written
 * out twice under a `v-if` / `v-else`.
 *
 * ## A violet disc, not the actor's avatar
 *
 * The phase 5 parity sweep put this back to legacy's treatment: a 36px disc
 * filled `primary-100` / `primary-600` (the `violet-100` / `violet-600` legacy
 * hardcoded, through the token ramp so dark mode is `primary-900/40` /
 * `primary-300`) holding the notification's type glyph.
 *
 * It used to render `UserAvatar` with the glyph as a small badge on its corner.
 * That is a strictly richer row and it is not what the rest of this shell
 * looks like, which is the whole point of the sweep — but it also had a
 * concrete defect: `ProfileResource` deliberately does not emit an avatar
 * (.ai/rules/resources.md), and `message_replace.name` is the only thing about
 * the actor in the payload, so every one of those avatars was an initials
 * fallback dressed as a photograph. The disc says "notification of this kind",
 * which is what the row actually knows.
 *
 * The timestamp and the unread dot sit on one line under the sentence, in
 * legacy's arrangement: `justify-between` puts the stamp at the reading start
 * and the dot at the end, mirrored by direction because both are logical.
 *
 * ## The sentence is assembled here and nowhere else
 *
 * The payload carries `message_key` and `message_replace` and no rendered text
 * at all — a stored row outlives the reader's locale, so the backend never
 * calls `__()` on it (.ai/rules/notifications.md). `t()` is therefore the only
 * thing that can turn a row into a sentence, and this component is where that
 * happens.
 *
 * `message_replace.name` is `(string) $actor?->name`, so a notification whose
 * actor has since been deleted arrives with an **empty string** rather than a
 * null. Substituting it would render ":name liked your pet Rex" as " liked your
 * pet Rex". The localized `notifications.someone` stands in — supplied on this
 * side, which is exactly where the rule says a "someone"-style fallback
 * belongs.
 *
 * ## Why the sentence carries an id it does not use itself
 *
 * `messageId` lands on the `<p>` below and is read by `NotificationItem`: that
 * component's mark-read button builds its accessible name with
 * `aria-labelledby` out of this paragraph, so the N buttons on screen are told
 * apart by name. The sentence stays computed in exactly one place — referencing
 * the rendered element is what keeps a second copy of
 * `t(message_key, message_replace)` from existing.
 *
 * Required, not optional: there is one caller, and a row whose sentence had no
 * id would quietly hand that button the same name as every other one.
 */
const { notification, messageId } = defineProps<{
    notification: InboxNotification;
    messageId: string;
}>();

const { t } = useTranslations();
const { tag } = useLocale();

/**
 * `type` is the payload's own label, and it is an open set: the resource falls
 * back to the notification class's basename for a row written before the
 * convention, so there is always a default.
 */
const ICONS: Record<string, Component> = {
    like: Heart,
    comment: MessageSquare,
    review: Star,
    message: Mail,
    report: Flag,
};

const icon = computed<Component>(() => ICONS[notification.type] ?? Bell);

const actorName = computed<string | null>(() => {
    const name = notification.message_replace.name?.trim();

    return name ? name : null;
});

const message = computed(() =>
    t(notification.message_key, {
        ...notification.message_replace,
        name: actorName.value ?? t('notifications.someone'),
    }),
);

const timestamp = computed(() =>
    formatRelative(notification.created_at, tag.value),
);
</script>

<template>
    <div class="flex w-full items-start gap-3 text-start">
        <div
            class="bg-primary-100 text-primary-600 dark:bg-primary-900/40 dark:text-primary-300 mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full"
        >
            <component :is="icon" class="size-4" aria-hidden="true" />
        </div>

        <div class="min-w-0 flex-1">
            <p
                :id="messageId"
                class="text-foreground text-sm leading-snug"
                :class="{ 'font-semibold': !notification.read }"
            >
                {{ message }}
            </p>
            <div class="mt-1 flex items-center justify-between gap-2">
                <time
                    :datetime="notification.created_at"
                    class="text-muted-foreground text-xs"
                >
                    {{ timestamp }}
                </time>
                <span
                    v-if="!notification.read"
                    class="bg-primary-500 size-2 shrink-0 rounded-full"
                    aria-hidden="true"
                />
            </div>
        </div>
    </div>
</template>
