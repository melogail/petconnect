import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    return toUrl(urlToCheck) === currentUrl;
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function fallbackAvatar(name: string) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}`;
}

export function formatConversationTimestamp(value: string | null) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const now = new Date();
    const isSameDay = date.toDateString() === now.toDateString();
    const diffInMs = now.getTime() - date.getTime();
    const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));

    if (isSameDay) {
        return new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        }).format(date);
    }

    if (diffInHours < 24 * 7) {
        return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(
            -Math.max(1, Math.floor(diffInHours / 24)),
            'day',
        );
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(date);
}

export function messagePreview(content: string, isOwnMessage = false) {
    const trimmed = content.trim() || 'No messages yet';

    return isOwnMessage ? `You: ${trimmed}` : trimmed;
}
