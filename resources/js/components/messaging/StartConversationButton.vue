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
 * **extend** its visible one where the control repeats. On a page with one of
 * these "Message" is unambiguous; in a feed grid every card renders one, and a
 * screen reader's button list is then N identical entries with nothing
 * distinguishing them. Optional, and omitting it leaves the name as the visible
 * text, which is why `profile/ProfileHeader` — the one call site that passes
 * recipient id and name only — is unaffected by anything here.
 *
 * ## Extend, never replace: the constraint on what may be passed
 *
 * **Every value passed here must contain the trigger's visible text as a
 * substring.** Speech input matches the words a user reads off the screen, so
 * a name that drops them stops the control being addressable by voice at all
 * (WCAG 2.5.3, Label in Name) — silently, with nothing visibly wrong and no
 * gate that goes red. "Able to differ" is the licence that produced the defect
 * and it is not what this prop is for: `Message Ruthe about Luna` extends the
 * visible "Message"; `Contact Owner` replaced it, and did so on the one page
 * that renders two of these.
 *
 * Both current names satisfy it in English — `pets/PetOwnerCard` passes
 * `messaging.send_message` → "Send Message" and `pets/PetDetailHeader` builds
 * the feed's `Message {owner} about {pet}` — and they stay distinct from each
 * other, which is the separate property that page needs.
 *
 * ## The Arabic half is open, and it is this component's to close, not a
 * caller's
 *
 * Containment holds **in English only**, and treat that as a latent failure
 * rather than a closed finding. The visible text below is a hardcoded English
 * "Message" while a caller may pass a translated name: under `ar`,
 * `messaging.send_message` is "إرسال الرسالة", which contains no "Message", so
 * the owner card's trigger fails 2.5.3 for an Arabic reader today. Only
 * `PetDetailHeader`'s label passes in every locale, and only because it is
 * itself untranslated English.
 *
 * Measured, not argued: on `/pets/10` under `ar` (`dir="rtl"`), in an isolated
 * build served from a throwaway database on 2026-09-06, the two triggers on
 * that page render the identical visible string "Message" while computing to
 * `Message Catharine Zulauf about Mose` (contains it) and `إرسال الرسالة`
 * (does not).
 *
 * No caller can fix this. The fix is to translate **this trigger's visible
 * text** (and the dialog title, description, field label and submit button
 * with it — this component is untranslated throughout, as `pages/Home.vue`
 * records), then check containment per locale, which is an explicit deliverable
 * of the scheduled i18n pass in `.ai/rules/lang.md`. Do not close it from a
 * call site by inventing an English-shaped key: that hides the gap without
 * removing it.
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
 * — so `profile/ProfileHeader`, the one call site that still omits the prop, is
 * unchanged. (It read "the two call sites that omit the prop" when the
 * measurement was taken; `pets/PetOwnerCard` and then `pets/PetDetailHeader`
 * have since started passing one. The bytes are the measurement and stand as
 * taken; only the count of omitting callers moved.) Adding
 * `triggerLabel: 'Message Ruthe about Ruthe'` takes it to 1189 bytes, the whole
 * 39-byte difference being ` aria-label="Message Ruthe about Ruthe"` on that
 * same element; nothing else moves.
 *
 * ## The icon appearance
 *
 * `appearance="icon"` renders the trigger as legacy's feed-card control
 * (`components/web/PetCard.vue`, the "Quick Message Dialog Trigger" block):
 * a 48px round brand-gradient button holding only the icon, added on the
 * user's instruction (2026-09-06). It has **no visible text**, so the
 * containment constraint above has nothing to contain and the `aria-label` is
 * the whole accessible name — which is why this branch supplies a default
 * (`Message {recipientName}`) rather than leaving the button nameless when a
 * caller omits `triggerLabel`. The same string goes on `title`, so a sighted
 * mouse user gets the tooltip legacy showed. The Arabic gap above is about the
 * visible "Message" of the default appearance; the icon appearance does not
 * add to it, and does not close it either.
 */
const {
    recipientId,
    recipientName,
    triggerLabel,
    appearance = 'default',
} = defineProps<{
    recipientId: number;
    recipientName: string;
    /**
     * Accessible name for the trigger. Defaults to its visible text,
     * "Message". Pass one where the control repeats on a page — and pass one
     * that **contains** "Message", per the containment constraint above.
     */
    triggerLabel?: string;
    /** `icon` is the round gradient button the feed card renders. */
    appearance?: 'default' | 'icon';
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <button
                v-if="appearance === 'icon'"
                type="button"
                :aria-label="triggerLabel ?? `Message ${recipientName}`"
                :title="triggerLabel ?? `Message ${recipientName}`"
                class="flex size-12 shrink-0 items-center justify-center rounded-full bg-linear-to-r from-violet-500 to-fuchsia-500 text-white transition hover:from-violet-600 hover:to-fuchsia-600 focus-visible:ring-[3px] focus-visible:ring-violet-500/50 focus-visible:outline-none"
            >
                <MessageCircle class="size-5" aria-hidden="true" />
            </button>
            <Button v-else :aria-label="triggerLabel">
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
