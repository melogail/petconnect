<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, MessageSquare } from '@lucide/vue';
import { computed } from 'vue';
import { TICKET_STATUS_VARIANT } from '@/components/support/ticketStatus';
import { Badge } from '@/components/ui/badge';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/datetime';
import { show as showTicket } from '@/routes/tickets';
import type { Ticket } from '@/types';

/**
 * One header in the member's ticket list: subject, category, status, how
 * long the thread is and when it last moved. The whole row is the link to
 * the ticket page; nothing of the thread itself is here.
 *
 * Labels come from `support.category.*` / `support.status.*` in the client
 * catalogue rather than the enums' English `label()` (.ai/rules/enums.md).
 */
const { ticket } = defineProps<{ ticket: Ticket }>();

const { t } = useTranslations();
const { tag } = useLocale();

const updatedOn = computed(() =>
    formatDate(ticket.last_activity_at ?? ticket.created_at, tag.value),
);

const messagesLabel = computed(() =>
    t(
        ticket.messages_count === 1
            ? 'support.messages_one'
            : 'support.messages_many',
        { count: String(ticket.messages_count) },
    ),
);
</script>

<template>
    <li>
        <Link
            :href="showTicket(ticket.id)"
            class="group border-border bg-card hover:bg-accent/40 focus-visible:ring-ring focus-visible:ring-offset-background flex items-center gap-4 rounded-[20px] border px-5 py-4 transition-[background-color,box-shadow] duration-200 outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
        >
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="truncate font-medium">{{ ticket.subject }}</h3>
                    <Badge :variant="TICKET_STATUS_VARIANT[ticket.status]">
                        {{ t(`support.status.${ticket.status}`) }}
                    </Badge>
                </div>
                <p
                    class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-2 text-xs"
                >
                    <span>{{
                        t('support.ticket_number', { id: ticket.id })
                    }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ t(`support.category.${ticket.category}`) }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{
                        t('support.updated_on', { date: updatedOn })
                    }}</span>
                </p>
            </div>

            <span
                class="text-muted-foreground hidden shrink-0 items-center gap-1 text-xs sm:flex"
            >
                <MessageSquare class="size-3.5" aria-hidden="true" />
                {{ messagesLabel }}
            </span>

            <ChevronRight
                class="text-muted-foreground size-4 shrink-0 transition-transform group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5"
                aria-hidden="true"
            />
        </Link>
    </li>
</template>
