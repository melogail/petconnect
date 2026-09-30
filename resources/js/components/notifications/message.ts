import type { UseTranslationsReturn } from '@/composables/useTranslations';
import type { InboxNotification } from '@/types';

/**
 * What a notification needs to be rendered as a sentence: the inbox row and
 * the broadcast payload both carry these two fields, and nothing else here.
 */
export type NotificationMessage = Pick<
    InboxNotification,
    'message_key' | 'message_replace'
>;

/**
 * The one place a notification's sentence is built, for the inbox row and the
 * realtime toast alike.
 *
 * `message_replace.name` is `(string) $actor?->name` on the server, so a
 * notification whose actor has since been deleted arrives with an **empty
 * string** rather than a null. Substituting it would render ":name liked your
 * pet Rex" as " liked your pet Rex". The localized `notifications.someone`
 * stands in — supplied on this side, which is where .ai/rules/notifications.md
 * says a "someone"-style fallback belongs.
 */
export function notificationMessage(
    notification: NotificationMessage,
    t: UseTranslationsReturn['t'],
): string {
    const name = notification.message_replace.name?.trim();

    return t(notification.message_key, {
        ...notification.message_replace,
        name: name || t('notifications.someone'),
    });
}
