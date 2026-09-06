<script setup lang="ts">
import { ref } from 'vue';
import CommentComposer from '@/components/comments/CommentComposer.vue';
import CommentComposerGate from '@/components/comments/CommentComposerGate.vue';
import CommentList from '@/components/comments/CommentList.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { provideMutationSurface } from '@/composables/useMutationSurface';
import { useTranslations } from '@/composables/useTranslations';
import type {
    Comment,
    CommentableType,
    ReportCategory,
    ReportReason,
    SelectOption,
} from '@/types';

/**
 * A commentable's discussion in a modal — legacy's `web/CommentsDialog`.
 *
 * Read from legacy rather than assumed, and legacy has **both** arrangements,
 * so this app now does too:
 *
 * - `web/PetCard.vue:703` wraps the card's comment control in this dialog and
 *   passes it the card's own comment preview through a `#trigger` scoped slot.
 *   The card never navigates to the listing to read a thread.
 * - `pages/pet/Show.vue` renders `pet/show/PetComments` inline, in a card, with
 *   no modal anywhere on the page.
 *
 * So the card opens a dialog and the listing page threads inline, and the two
 * share the row, reply, edit, delete and report components rather than each
 * having their own. What was here before — a link from the card to
 * `pets.show` — collapsed the two into one.
 *
 * ## The trigger is a slot, as it is in legacy
 *
 * `DialogTrigger as-child` around a default slot, so the caller supplies the
 * button and keeps its own labelling. Legacy passed an `open` function down a
 * scoped slot instead; `DialogTrigger` is the same arrangement expressed with
 * the primitive, and it brings Escape, outside-click dismissal, focus trapping
 * and focus restoration with it rather than leaving them to the caller.
 *
 * ## It fetches its own thread
 *
 * A feed card carries at most `petconnect.pets.home_comment_preview` roots and
 * **no replies at all**, so rendering the prop alone would show a truncated
 * discussion with every reply hidden behind a counter. `CommentList` fetches
 * page one from `comments.index` — public, plain JSON — as the dialog opens,
 * and the card's preview is only the seed that keeps the first paint from being
 * empty. That fetch is triggered by `CommentList` mounting, not by a watcher
 * here: reka-ui unmounts a closed `DialogContent`, so mounting *is* opening,
 * and the alternative would have had to wait a tick for the portal.
 *
 * ## Why a surface, and not a page reload
 *
 * Every write in here would otherwise redirect back to `Home`, whose `pets`
 * prop is an `Inertia::scroll()` merge prop: a full visit drops a reader on
 * page 4 back to page 1 and remounts the page component underneath the open
 * dialog, and a partial reload of `pets` duplicates every card. See
 * `composables/useMutationSurface.ts`, which carries the mechanism. The surface
 * provided here narrows every comment mutation to `only: ['errors']` — enough
 * for `<Form>` to render a 422, nothing that merges — and refreshes this list
 * afterwards.
 *
 * ## The trigger's counter moves on a `posted` / `deleted`, not on a reload
 *
 * The **card's** counter is a feed prop this dialog deliberately does not
 * reload, so it cannot be replaced — but it can be adjusted, and legacy did
 * exactly that: its dialog emitted on each write and the card kept a local
 * `+1` / `-1`. This emits `posted` and `deleted` for the same purpose, and a
 * trigger that shows a count holds the offset and adds it to its prop.
 *
 * What was tried and dropped is narrower than "a live count": feeding
 * `comments.index`'s `meta.total` into the card would have been wrong, because
 * the endpoint pages **roots** while the card shows `comments_count`, which
 * counts replies too. The emits carry a delta rather than a total and are not
 * subject to that — `deleted` carries `1 + replies_count` off the row itself,
 * because deleting a root takes its replies with it in one transaction.
 *
 * ## The thread scrolls, so the thread is focusable
 *
 * The list sits in its own `overflow-y-auto` region between a fixed header and
 * a fixed composer, and a scroll container that cannot take focus cannot be
 * scrolled by keyboard: Chrome and Safari do not put one in the tab order on
 * their own (Firefox has since 51), so once focus has passed the last row the
 * wheel and the touch drag were the only ways left to move the thread.
 * `tabindex="0"` is what gives it a stop. The absent attribute was observed in
 * this dialog's rendered DOM by the agent that reported it; the browser
 * behaviour above is the standing reason, not something re-measured here.
 *
 * The region with the attribute **was** measured, 2026-09-06, on a built copy
 * of this tree served against a throwaway sqlite and driven over CDP: opened
 * from a feed card, the region reports `scrollHeight` 938 against
 * `clientHeight` 465 — so it genuinely overflows — and six **trusted**
 * `Input.dispatchKeyEvent` ArrowDowns moved its `scrollTop` from 0 to 240.
 * `Accessibility.getFullAXTree` returns it as `region` with the computed name
 * "Comments", which is the name read out of the accessibility tree rather than
 * inferred from the attribute.
 *
 * The tab stop is paid for once per open dialog, not once per comment, which is
 * what makes it cheap here — contrast the reported-comment badge in
 * `CommentBody`, whose focusability is argued on the opposite footing because
 * it is a per-row stop. `role="region"` and an `aria-label` come with it
 * because a bare focusable `<div>` is an unnamed stop, and the label is
 * `comments.comments` rather than the dialog's own title string so the two do
 * not announce as the same thing twice.
 *
 * Consequence, recorded because it is a behaviour change and not a side
 * effect: this region is now the first tabbable node inside `DialogContent` —
 * the header holds none and the close button is rendered after the slot — so
 * opening focus should land here instead of on the first control inside the
 * first comment row. Focus starting at the top of the thread with the arrow
 * keys already live is the better of the two, but it is a change. Predicted by
 * reading `reka-ui/dist/FocusScope/FocusScope.js` — on an un-prevented
 * `focusScope.autoFocusOnMount` it calls `focusFirst` over
 * `getTabbableCandidates`, a walker that accepts any node with `tabIndex >= 0`
 * in DOM order — and then **confirmed by opening the dialog** in the CDP run
 * above: `document.activeElement` is this region immediately after it opens,
 * with no `focus()` call of our own.
 */
const { comments = [], subject } = defineProps<{
    commentableType: CommentableType;
    commentableId: number;
    /** What the thread is about — a listing's name, shown in the title. */
    subject: string;
    /** The card's bounded preview: a seed for the first paint, not the thread. */
    comments?: Comment[];
    canInteract: boolean;
    /** `petconnect.comments.max_length`, when the page was shipped it. */
    maxLength?: number | null;
    reportCategories?: SelectOption<ReportCategory>[];
    reportReasons?: SelectOption<ReportReason>[];
}>();

/**
 * What was written in here, for a trigger that shows a comment count. The count
 * on a feed card is a page prop this dialog deliberately does not reload — see
 * the surface below — so the caller holds the delta itself. `deleted` carries
 * `1 + replies_count`, because deleting a root takes its replies with it.
 */
const emit = defineEmits<{
    posted: [];
    deleted: [count: number];
}>();

const { t } = useTranslations();

const open = ref(false);
const list = ref<InstanceType<typeof CommentList> | null>(null);

provideMutationSurface({
    visit: { only: ['errors'], preserveState: true },
    onMutated: () => {
        void list.value?.reload();
    },
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>

        <DialogContent class="flex max-h-[80vh] flex-col sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle class="text-xl">
                    {{ t('comments.comments_for', { name: subject }) }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('comments.share_thoughts', { name: subject }) }}
                </DialogDescription>
            </DialogHeader>

            <div
                role="region"
                tabindex="0"
                :aria-label="t('comments.comments')"
                class="focus-visible:ring-ring/50 -me-2 min-h-0 flex-1 overflow-y-auto rounded-sm py-4 pe-2 focus-visible:ring-[3px] focus-visible:outline-none"
            >
                <CommentList
                    ref="list"
                    :comments="comments"
                    :commentable-type="commentableType"
                    :commentable-id="commentableId"
                    :max-length="maxLength"
                    :can-interact="canInteract"
                    :report-categories="reportCategories"
                    :report-reasons="reportReasons"
                    @posted="emit('posted')"
                    @deleted="emit('deleted', $event)"
                />
            </div>

            <div class="border-border/50 border-t pt-4">
                <CommentComposerGate>
                    <CommentComposer
                        :commentable-type="commentableType"
                        :commentable-id="commentableId"
                        :max-length="maxLength"
                        @posted="emit('posted')"
                    />
                </CommentComposerGate>
            </div>
        </DialogContent>
    </Dialog>
</template>
