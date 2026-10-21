<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { TICKET_TYPES } from '@/components/support/ticketTypes';
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
import { store as storeContact } from '@/routes/contact';

/**
 * The support page's "Email us" control: a button that opens the contact form
 * in a dialog and posts it to `contact.store`.
 *
 * ## Who is asking decides the form
 *
 * A guest is asked for a name and a reply address, both required. A signed-in
 * member is not asked — `auth.user` already carries both and the backend
 * copies them off the account, so the two fields are not rendered at all
 * rather than pre-filled and locked. The server mirrors the split:
 * `StoreContactRequest` requires both from a guest and excludes them from a
 * member, so a stale tab cannot send a member's typed-over address.
 *
 * `auth.user` is typed non-nullable and is null for guests (.ai/rules/types.md);
 * this component is mounted on a public page, so it is guarded at runtime.
 *
 * ## The topics are a client constant
 *
 * `support` is a `Route::inertia()` route with no props, so unlike
 * `ReportDialog` there is no option list to receive. `ticketTypes.ts` spells
 * the enum's backing values once, with catalogue keys for labels so the select
 * reads in Arabic too.
 */
const open = defineModel<boolean>('open', { default: false });

const page = usePage();
const { t } = useTranslations();

/** A member's name and address come off the account; only a guest types them. */
const isSignedIn = computed(() => Boolean(page.props.auth.user));

const options = { preserveScroll: true };
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">
                {{ t('support.email_us') }}
                <ArrowRight class="size-4 rtl:rotate-180" aria-hidden="true" />
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle>{{ t('support.form_title') }}</DialogTitle>
                <DialogDescription>
                    {{ t('support.form_description') }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="storeContact.form()"
                reset-on-success
                :options="options"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <template v-if="!isSignedIn">
                    <div class="grid gap-2">
                        <Label for="contact-name">{{
                            t('support.name')
                        }}</Label>
                        <Input
                            id="contact-name"
                            name="name"
                            type="text"
                            required
                            autocomplete="name"
                            maxlength="255"
                            :placeholder="t('support.name_placeholder')"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="contact-email">
                            {{ t('support.email') }}
                        </Label>
                        <Input
                            id="contact-email"
                            name="email"
                            type="email"
                            required
                            autocomplete="email"
                            maxlength="255"
                            :placeholder="t('support.email_placeholder')"
                        />
                        <InputError :message="errors.email" />
                    </div>
                </template>

                <div class="grid gap-2">
                    <Label for="contact-ticket-type">
                        {{ t('support.ticket_type') }}
                    </Label>
                    <Select name="ticket_type" required>
                        <SelectTrigger id="contact-ticket-type" class="w-full">
                            <SelectValue
                                :placeholder="t('support.select_ticket_type')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="ticketType in TICKET_TYPES"
                                :key="ticketType.value"
                                :value="ticketType.value"
                            >
                                {{ t(ticketType.labelKey) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.ticket_type" />
                </div>

                <div class="grid gap-2">
                    <Label for="contact-subject">
                        {{ t('support.subject') }}
                    </Label>
                    <Input
                        id="contact-subject"
                        name="subject"
                        type="text"
                        required
                        maxlength="255"
                        :placeholder="t('support.subject_placeholder')"
                    />
                    <InputError :message="errors.subject" />
                </div>

                <div class="grid gap-2">
                    <Label for="contact-message">
                        {{ t('support.message') }}
                    </Label>
                    <Textarea
                        id="contact-message"
                        name="message"
                        rows="5"
                        required
                        maxlength="2000"
                        class="resize-none"
                        :placeholder="t('support.message_placeholder')"
                    />
                    <InputError :message="errors.message" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="open = false"
                    >
                        {{ t('common.cancel') }}
                    </Button>
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
