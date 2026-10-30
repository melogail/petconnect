<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import TicketMessageBubble from '@/components/support/TicketMessageBubble.vue';
import { TICKET_STATUS_VARIANT } from '@/components/support/ticketStatus';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/datetime';
import {
    close as closeTicket,
    index as ticketsIndex,
    reply as replyToTicket,
} from '@/routes/tickets';
import type { Ticket, TicketBounds, TicketMessage } from '@/types';

/**
 * One ticket: its header, the thread oldest first, and — while it is not
 * closed — a reply composer and a "mark as resolved" control.
 *
 * `canReply` is TicketPolicy::reply decided once on the server (owner and not
 * closed), so the page does not re-derive the rule; `reply` and `close`
 * answer `back()`, so both land here with the thread refreshed. The composer's
 * `maxlength` is `ticketBounds.max_message_length`, the server's own rule.
 */
const { ticket, messages } = defineProps<{
    ticket: Ticket;
    messages: TicketMessage[];
    canReply: boolean;
    ticketBounds: TicketBounds;
}>();

const { t } = useTranslations();
const { tag } = useLocale();

const openedOn = computed(() => formatDate(ticket.created_at, tag.value));

const options = { preserveScroll: true };
</script>

<template>
    <div class="flex flex-col space-y-8">
        <Head :title="ticket.subject" />

        <div class="space-y-4">
            <Button as-child variant="ghost" size="sm" class="-ms-2">
                <Link :href="ticketsIndex()">
                    <ArrowLeft
                        class="size-4 rtl:rotate-180"
                        aria-hidden="true"
                    />
                    {{ t('support.back_to_tickets') }}
                </Link>
            </Button>

            <!-- The document's h1 is the settings banner in `layouts/settings/Layout.vue`. -->
            <header class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-serif text-2xl leading-tight font-medium">
                        {{ ticket.subject }}
                    </h2>
                    <Badge :variant="TICKET_STATUS_VARIANT[ticket.status]">
                        {{ t(`support.status.${ticket.status}`) }}
                    </Badge>
                </div>
                <p
                    class="text-muted-foreground flex flex-wrap items-center gap-x-2 text-sm"
                >
                    <span>{{
                        t('support.ticket_number', { id: ticket.id })
                    }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ t(`support.category.${ticket.category}`) }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{
                        t('support.opened_on', { date: openedOn })
                    }}</span>
                </p>
                <p class="text-soft-foreground text-sm">
                    {{ t(`support.status_hint.${ticket.status}`) }}
                </p>
            </header>
        </div>

        <ol
            class="flex flex-col gap-4"
            :aria-label="t('support.tickets_title')"
        >
            <TicketMessageBubble
                v-for="message in messages"
                :key="message.id"
                :message="message"
            />
        </ol>

        <section
            v-if="canReply"
            class="border-border bg-card shadow-card space-y-4 rounded-[20px] border p-5"
        >
            <Form
                v-bind="replyToTicket.form(ticket.id)"
                reset-on-success
                :options="options"
                class="space-y-3"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="ticket-reply">{{ t('support.reply') }}</Label>
                    <Textarea
                        id="ticket-reply"
                        name="body"
                        rows="4"
                        required
                        :maxlength="ticketBounds.max_message_length"
                        class="resize-none"
                        :placeholder="t('support.reply_placeholder')"
                    />
                    <InputError :message="errors.body" />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{
                            processing
                                ? t('support.sending')
                                : t('support.send_reply')
                        }}
                    </Button>

                    <Form
                        v-bind="closeTicket.form(ticket.id)"
                        :options="options"
                        v-slot="{ processing: closing }"
                    >
                        <Button
                            type="submit"
                            variant="secondary"
                            :disabled="closing"
                            :title="t('support.close_ticket_hint')"
                        >
                            <Spinner v-if="closing" />
                            <CheckCircle2
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{ t('support.close_ticket') }}
                        </Button>
                    </Form>
                </div>
            </Form>
        </section>
    </div>
</template>
