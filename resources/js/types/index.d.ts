import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: InertiaResource<User> | null;
}

export type InertiaResource<T> = T | { data: T };

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface MessagingPreviewItem {
    conversation_id: number;
    peer: { id?: number | null; name: string; avatar?: string | null };
    sender_id?: number | null;
    preview: string;
    time: string;
    unread: boolean;
}

export interface MessagingSummary {
    unread_count: number;
    previews: MessagingPreviewItem[];
}

export interface NotificationItem {
    id: string;
    type: string;
    text: string;
    url?: string | null;
    time: string;
    read: boolean;
    created_at?: string | null;
}

export interface NotificationsSummary {
    unread_count: number;
    items: NotificationItem[];
}

export interface MessagingConversation {
    id: number;
    type: string;
    last_message_at: string | null;
    users?: User[];
    created_at?: string | null;
    updated_at?: string | null;
    can?: {
        update: boolean;
        delete: boolean;
    };
}

export interface MessagingMessage {
    id: number;
    sender_id: number;
    conversation?: Pick<
        MessagingConversation,
        'id' | 'type' | 'last_message_at'
    > | null;
    sender?: User | null;
    pinned_by?: User | null;
    content: string;
    type: string;
    status: string;
    read_at: string | null;
    is_pinned: boolean;
    created_at: string | null;
    updated_at: string | null;
    can?: {
        update: boolean;
        delete: boolean;
    };
}

export interface MessagingInboxRow {
    conversation: MessagingConversation;
    peer: User | null;
    last_message: MessagingMessage | null;
    unread: boolean;
}

export interface PaginatedResponse<T> {
    data: T[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    meta?: Record<string, unknown>;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    locale: string;
    dir: 'ltr' | 'rtl';
    translations: Record<string, string>;
    quote: { message: string; author: string };
    flash: {
        success: string | null;
        error: string | null;
    };
    auth: Auth;
    messaging: MessagingSummary | null;
    notifications: NotificationsSummary | null;
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email?: string | null;
    avatar?: string | null;
    avatar_url?: string | null;
    phone?: string | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    zip?: string | null;
    country?: string | null;
    locale?: string | null;
    rating?: number | string | null;
    email_verified_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export type BreadcrumbItemType = BreadcrumbItem;
