<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useTranslations } from '@/composables/useTranslations';
import type { NotificationItem } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { Bell, CheckCheck, ThumbsUp, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    notifications: NotificationItem[];
    unreadCount: number;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t, dir } = useTranslations();

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const hasNotifications = computed(() => props.notifications.length > 0);

const sheetSide = computed(() => (dir.value === 'rtl' ? 'left' : 'right'));

const unreadLabel = computed(() =>
    props.unreadCount === 1
        ? t('notifications.unread_one', { count: props.unreadCount })
        : t('notifications.unread_many', { count: props.unreadCount }),
);

const inertiaOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['notifications', 'flash'] as string[],
};

const markAsRead = (notification: NotificationItem) => {
    if (notification.read) {
        if (notification.url) {
            router.visit(notification.url);
        }
        return;
    }

    router.post(
        route('notifications.read', notification.id),
        {},
        {
            ...inertiaOptions,
            onSuccess: () => {
                if (notification.url) {
                    router.visit(notification.url);
                }
            },
        },
    );
};

const markAllAsRead = () => {
    if (props.unreadCount === 0) {
        return;
    }

    router.post(route('notifications.read-all'), {}, inertiaOptions);
};

const deleteAll = () => {
    if (!hasNotifications.value) {
        return;
    }

    router.delete(route('notifications.destroy-all'), inertiaOptions);
};
</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetTrigger as-child>
            <button
                type="button"
                class="relative rounded-full p-2 transition-colors duration-200 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800"
                :aria-label="
                    unreadCount > 0
                        ? `${t('nav.notifications')} (${unreadLabel})`
                        : t('nav.notifications')
                "
            >
                <span class="sr-only">{{ t('nav.notifications') }}</span>
                <Bell
                    class="h-6 w-6 text-gray-600 transition-colors dark:text-gray-300"
                    :class="{
                        'text-violet-600 dark:text-violet-400': open,
                    }"
                />
                <span
                    v-if="unreadCount > 0"
                    class="absolute end-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] text-white shadow-sm"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
            </button>
        </SheetTrigger>

        <SheetContent
            :side="sheetSide"
            class="flex w-full flex-col gap-0 p-0 sm:max-w-md"
        >
            <SheetHeader class="border-border border-b px-6 py-4 text-start">
                <div class="flex items-start justify-between gap-3 pe-6">
                    <div class="space-y-1">
                        <SheetTitle>{{
                            t('notifications.notifications')
                        }}</SheetTitle>
                        <SheetDescription>
                            <span v-if="unreadCount > 0">
                                {{ unreadLabel }}
                            </span>
                            <span v-else>{{
                                t('notifications.all_caught_up')
                            }}</span>
                        </SheetDescription>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="gap-1.5"
                        :disabled="unreadCount === 0"
                        @click="markAllAsRead"
                    >
                        <CheckCheck class="h-4 w-4" />
                        {{ t('notifications.mark_all_as_read') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-destructive hover:text-destructive gap-1.5"
                        :disabled="!hasNotifications"
                        @click="deleteAll"
                    >
                        <Trash2 class="h-4 w-4" />
                        {{ t('notifications.delete_all') }}
                    </Button>
                </div>
            </SheetHeader>

            <div class="flex-1 overflow-y-auto">
                <div v-if="hasNotifications" class="divide-border divide-y">
                    <button
                        v-for="notification in notifications"
                        :key="notification.id"
                        type="button"
                        class="hover:bg-muted/50 flex w-full items-start gap-3 px-6 py-4 text-start transition-colors"
                        :class="{
                            'bg-violet-50/70 dark:bg-violet-950/20':
                                !notification.read,
                        }"
                        @click="markAsRead(notification)"
                    >
                        <div
                            class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-300"
                        >
                            <ThumbsUp
                                v-if="notification.type === 'like'"
                                class="h-4 w-4"
                            />
                            <Bell v-else class="h-4 w-4" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                class="text-foreground text-sm leading-snug"
                                :class="{
                                    'font-semibold': !notification.read,
                                }"
                            >
                                {{ notification.text }}
                            </p>
                            <div
                                class="mt-1 flex items-center justify-between gap-2"
                            >
                                <span class="text-muted-foreground text-xs">
                                    {{ notification.time }}
                                </span>
                                <span
                                    v-if="!notification.read"
                                    class="h-2 w-2 rounded-full bg-violet-500"
                                />
                            </div>
                        </div>
                    </button>
                </div>

                <div
                    v-else
                    class="flex h-full min-h-64 flex-col items-center justify-center gap-3 px-6 text-center"
                >
                    <div
                        class="bg-muted text-muted-foreground flex h-14 w-14 items-center justify-center rounded-full"
                    >
                        <Bell class="h-6 w-6" />
                    </div>
                    <div class="space-y-1">
                        <p class="text-foreground text-sm font-medium">
                            {{ t('notifications.no_notifications_yet') }}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{ t('notifications.empty_hint') }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="hasNotifications"
                class="border-border border-t px-6 py-3 text-center"
            >
                <Link
                    :href="route('home')"
                    class="text-xs font-medium text-violet-600 hover:underline dark:text-violet-400"
                    @click="isOpen = false"
                >
                    {{ t('notifications.close_and_continue') }}
                </Link>
            </div>
        </SheetContent>
    </Sheet>
</template>
