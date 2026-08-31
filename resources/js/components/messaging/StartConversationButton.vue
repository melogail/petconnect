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
 * "create a conversation". Both refusals — messaging yourself, and a recipient
 * who is not accepting messages — come back on `recipient_id`.
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
