<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { store as storeTicket } from '@/routes/tickets';
import type { TicketCategory } from '@/types';

/**
 * "Open a ticket": a trigger button and the ticket form in a dialog, posted
 * to `tickets.store`. Members only — the route is behind `auth` and the
 * account supplies the sender's name and address, so neither is asked for.
 * Mount it only where `auth.user` is known to be set (the tickets page, and
 * the signed-in branch of the support page); a guest gets `ContactDialog`.
 *
 * ## The category is a reka `Select` over a hidden input
 *
 * `<Form>` serialises the DOM, and reka's Select renders no form field of its
 * own, so the chosen value is mirrored into `<input type="hidden"
 * name="category">`. The option list is the `TicketCategory` union, labelled
 * from `support.category.*` so each locale reads its own words
 * (.ai/rules/enums.md on why the enum's `label()` is not shipped).
 *
 * On success the server redirects to the new ticket's page; the dialog
 * closes on the way.
 */
const { maxMessageLength = 2000 } = defineProps<{
    /** `ticketBounds.max_message_length`; the config default where a page has no props. */
    maxMessageLength?: number;
}>();

const { t } = useTranslations();

const open = ref(false);
const category = ref<TicketCategory | ''>('');

const CATEGORIES: TicketCategory[] = [
    'technical_issue',
    'account_issue',
    'listing_issue',
    'missing_category',
    'missing_breed',
    'feedback',
    'other',
];

const options = { preserveScroll: true };

function onSuccess(): void {
    open.value = false;
    category.value = '';
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button type="button">
                <Plus class="size-4" aria-hidden="true" />
                {{ t('support.new_ticket') }}
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('support.new_ticket') }}</DialogTitle>
                <DialogDescription>
                    {{ t('support.new_ticket_description') }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="storeTicket.form()"
                reset-on-success
                :options="options"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="onSuccess"
            >
                <div class="grid gap-2">
                    <Label for="ticket-category">{{
                        t('support.category')
                    }}</Label>
                    <Select v-model="category">
                        <SelectTrigger id="ticket-category" class="w-full">
                            <SelectValue
                                :placeholder="t('support.select_category')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="value in CATEGORIES"
                                :key="value"
                                :value="value"
                            >
                                {{ t(`support.category.${value}`) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <input type="hidden" name="category" :value="category" />
                    <InputError :message="errors.category" />
                </div>

                <div class="grid gap-2">
                    <Label for="ticket-subject">{{
                        t('support.subject')
                    }}</Label>
                    <Input
                        id="ticket-subject"
                        name="subject"
                        type="text"
                        required
                        maxlength="255"
                        :placeholder="t('support.subject_placeholder')"
                    />
                    <InputError :message="errors.subject" />
                </div>

                <div class="grid gap-2">
                    <Label for="ticket-message">{{
                        t('support.message')
                    }}</Label>
                    <Textarea
                        id="ticket-message"
                        name="message"
                        rows="5"
                        required
                        :maxlength="maxMessageLength"
                        class="resize-none"
                        :placeholder="t('support.message_placeholder')"
                    />
                    <InputError :message="errors.message" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{
                            processing
                                ? t('support.sending')
                                : t('support.send')
                        }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
