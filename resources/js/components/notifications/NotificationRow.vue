<script setup lang="ts">
import { Bell, Flag, Heart, Mail, MessageSquare, Star } from '@lucide/vue';
import { computed, type Component } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatRelative } from '@/lib/datetime';
import type { InboxNotification } from '@/types';

/**
 * What one notification *looks* like — avatar, sentence, timestamp, unread dot.
 *
 * Purely presentational, and deliberately separate from `NotificationItem`:
 * a row with a deep link is wrapped in a `<Link>` and a row without one is
 * wrapped in a `<div>`, and without this split that whole body would be written
 * out twice under a `v-if` / `v-else`.
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
 */
const { notification } = defineProps<{ notification: InboxNotification }>();

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
        <div class="relative shrink-0">
            <UserAvatar
                :name="actorName ?? t('notifications.someone')"
                class="size-9"
            />
            <span
                class="bg-background ring-background absolute -end-1 -bottom-1 rounded-full p-1 ring-2"
            >
                <component
                    :is="icon"
                    class="text-muted-foreground size-3"
                    aria-hidden="true"
                />
            </span>
        </div>

        <div class="min-w-0 flex-1">
            <p
                class="text-sm"
                :class="
                    notification.read ? 'text-muted-foreground' : 'font-medium'
                "
            >
                {{ message }}
            </p>
            <p class="text-muted-foreground mt-0.5 text-xs">
                <time :datetime="notification.created_at">{{ timestamp }}</time>
            </p>
        </div>

        <span
            v-if="!notification.read"
            class="bg-primary mt-2 size-2 shrink-0 rounded-full"
            aria-hidden="true"
        />
    </div>
</template>
