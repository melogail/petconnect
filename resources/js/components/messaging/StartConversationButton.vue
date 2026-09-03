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
 *
 * `triggerLabel` exists because the trigger's accessible name has to be able to
 * differ from its visible one where the control repeats. On a listing page
 * there is one of these and "Message" is unambiguous; in a feed grid every card
 * renders one, and a screen reader's button list is then N identical entries
 * with nothing distinguishing them. Optional, and omitting it leaves the name
 * as the visible text, so neither existing call site (`profile/ProfileHeader`,
 * `pets/PetOwnerCard`, both passing recipient id and name only) changes.
 *
 * It is a **prop and not a fall-through attribute**, which is the whole reason
 * it exists rather than being left to the call site. This component's root is
 * `Dialog`, i.e. reka-ui's `DialogRoot`, which sets `inheritAttrs: false` and
 * whose render function is a bare `renderSlot` with no root element to receive
 * anything (read in `node_modules/reka-ui/dist/Dialog/DialogRoot.js`, reka-ui
 * 2.9.8). An `aria-label` written on `<StartConversationButton>` is therefore
 * dropped in silence — no warning, no error, and a button that is still
 * nameless. The technique does work on `PetLikeButton`, whose root is `Button`
 * → `Primitive`, so reasoning by analogy between the two produces exactly this
 * defect. Verified the prop lands by SSR-rendering this component through
 * `vue/server-renderer` and reading the emitted trigger, rather than by
 * reasoning about forwarding. At `recipientId: 7, recipientName: 'Ruthe'` the
 * emitted `<button data-slot="dialog-trigger">` is 1150 bytes and carries no
 * `aria-label`, byte-for-byte equal to the same render of this file at 87e21ff
 * — so the two call sites that omit the prop are unchanged. Adding
 * `triggerLabel: 'Message Ruthe about Ruthe'` takes it to 1189 bytes, the whole
 * 39-byte difference being ` aria-label="Message Ruthe about Ruthe"` on that
 * same element; nothing else moves.
 */
const { recipientId, recipientName, triggerLabel } = defineProps<{
    recipientId: number;
    recipientName: string;
    /**
     * Accessible name for the trigger. Defaults to its visible text,
     * "Message". Pass one where the control repeats on a page.
     */
    triggerLabel?: string;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button :aria-label="triggerLabel">
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
