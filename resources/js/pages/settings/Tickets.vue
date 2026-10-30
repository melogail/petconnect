<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { LifeBuoy } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import NewTicketDialog from '@/components/support/NewTicketDialog.vue';
import TicketRow from '@/components/support/TicketRow.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated, Ticket, TicketBounds } from '@/types';

/**
 * The member's support tickets, inside the settings shell: a list of
 * headers, each a link to its ticket page, and the "Open a ticket" dialog.
 *
 * The list is `tickets.index`'s paginator, paged by `?tickets=` so the
 * dialog's validation errors and the page cursor never fight over a query
 * key. A new ticket redirects to its own page, so nothing here refreshes.
 */
defineProps<{
    tickets: Paginated<Ticket>;
    ticketBounds: TicketBounds;
}>();

const { t } = useTranslations();
</script>

<template>
    <div class="flex flex-col space-y-8">
        <Head :title="t('support.tickets_title')" />

        <!-- The document's h1 is the settings banner in `layouts/settings/Layout.vue`. -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="t('support.tickets_title')"
                :description="t('support.tickets_description')"
            />
            <NewTicketDialog
                :max-message-length="ticketBounds.max_message_length"
            />
        </div>

        <EmptyState
            v-if="tickets.data.length === 0"
            :icon="LifeBuoy"
            :title="t('support.no_tickets')"
            :description="t('support.no_tickets_hint')"
        />

        <template v-else>
            <ul class="space-y-3">
                <TicketRow
                    v-for="ticket in tickets.data"
                    :key="ticket.id"
                    :ticket="ticket"
                />
            </ul>

            <Pagination :links="tickets.meta.links" :only="['tickets']" />
        </template>
    </div>
</template>
