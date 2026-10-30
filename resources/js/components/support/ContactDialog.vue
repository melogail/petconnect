<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { store as storeContact } from '@/routes/contact';

/**
 * The guest channel: "Send us a message", a trigger button and a one-off
 * contact form in a dialog, posted to `contact.store`. A visitor has no
 * account to read a name and address off, so both are asked for, and no
 * thread to follow, so there is no category — the team answers by email.
 *
 * A signed-in member is offered `NewTicketDialog` instead; the support page
 * decides which of the two to mount from `auth.user`.
 *
 * The composer bound is the config default: this mounts on
 * `Route::inertia('support')`, which carries no props (see `lib/support.ts`).
 */
const { t } = useTranslations();

const open = ref(false);

const options = { preserveScroll: true };

function onSuccess(): void {
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button type="button">
                <Mail class="size-4" aria-hidden="true" />
                {{ t('support.send_message') }}
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('support.send_message') }}</DialogTitle>
                <DialogDescription>
                    {{ t('support.contact_description') }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="storeContact.form()"
                reset-on-success
                :options="options"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="onSuccess"
            >
                <div class="grid gap-2">
                    <Label for="contact-name">{{ t('support.name') }}</Label>
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
                    <Label for="contact-email">{{ t('support.email') }}</Label>
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

                <div class="grid gap-2">
                    <Label for="contact-subject">{{
                        t('support.subject')
                    }}</Label>
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
                    <Label for="contact-message">{{
                        t('support.message')
                    }}</Label>
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
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{
                            processing
                                ? t('support.sending')
                                : t('support.send_message')
                        }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
