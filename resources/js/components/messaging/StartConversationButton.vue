<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store as storeConversation } from '@/routes/conversations';

/**
 * Open a thread with somebody.
 *
 * `conversations.store` reuses the existing private thread when there is one,
 * then redirects to `conversations.show`, so this is "message them" rather than
 * "create a conversation".
 *
 * Only one refusal comes back on `recipient_id`: messaging yourself, which
 * StoreConversationRequest's `Rule::notIn` answers with a 422 and its own
 * message. A recipient who does not exist and one who has been deactivated
 * both answer 404 from route resolution instead — identically, on purpose, so
 * the client cannot be handed a message saying which — and there is therefore
 * no field error to render for either. Over an Inertia POST that 404 surfaces
 * as the client-side error modal rather than a rendered page; pre-existing and
 * application-wide, see .ai/rules/messaging.md.
 *
 * The `recipient_id` InputError stays for the self-message 422 (and for a
 * `required`/`integer` failure on the hidden input). Both call sites hide this
 * button from the subject themselves, but that guard is a client-side
 * derivation off props a cached or prefetched page can serve stale, so the
 * server rule is the one that decides and its message needs somewhere to land.
 */
const { recipientId, recipientName } = defineProps<{
    recipientId: number;
    recipientName: string;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button>
                <MessageCircle class="size-4" />
                Message
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>Message {{ recipientName }}</DialogTitle>
                <DialogDescription>
                    We will open your existing thread if you already have one.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="storeConversation.form()"
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="recipient_id" :value="recipientId" />
                <InputError :message="errors.recipient_id" />

                <div class="grid gap-2">
                    <Label for="initial-message">
                        First message (optional)
                    </Label>
                    <Textarea
                        id="initial-message"
                        name="initial_message"
                        rows="4"
                        placeholder="Say hello…"
                    />
                    <InputError :message="errors.initial_message" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Open thread
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
