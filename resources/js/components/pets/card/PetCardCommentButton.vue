<script setup lang="ts">
import { MessageSquare } from '@lucide/vue';
import { computed, ref } from 'vue';
import CommentsDialog from '@/components/comments/CommentsDialog.vue';
import { countLabel } from '@/components/pets/card/labels';
import { Button } from '@/components/ui/button';
import type {
    Comment,
    ReportCategory,
    ReportReason,
    SelectOption,
} from '@/types';

/**
 * `comments_count`, as the trigger for the listing's comments dialog.
 *
 * A dialog and **not** a link, which is what this was. Legacy's feed card
 * (`web/PetCard.vue:703`) wraps exactly this control in `CommentsDialog` and
 * hands it the card's own comment preview; it never navigates to the listing
 * page to read a thread. Reading legacy is what settled it — the previous
 * version of this file argued for the link from the props the dialog would
 * need, which turned out to be an argument for shipping those props rather than
 * for a different control.
 *
 * ## The props the dialog needs, and where they come from
 *
 * `maxLength`, `reportCategories` and `reportReasons` are page props, read off
 * `page.props` by `PetListingCard` and handed down through `PetCardActions`.
 * Both pages that mount a feed card ship all three — `Home` and `profile.show`
 * — as `pets.show` does for the inline thread. Established 2026-09-06 by
 * reading the `Inertia::render()` payloads of `HomeController::index`,
 * `ProfileController::show` and `PetController::show`, each of which sends
 * `reportCategories`, `reportReasons` and `commentBounds`; not by rendering
 * them here. The report control on a dialog opened from a feed card was
 * separately observed working end to end this phase, through to a `reports`
 * row, by the agent that made the fix.
 *
 * `Home` carrying the two report lists is the repair of a parity regression,
 * so read those keys as live consumers of this component rather than as
 * unused controller props: removing one turns its control off on that page
 * silently. The mechanism is why it is silent, and it is unchanged — the props
 * stay optional the whole way down, and **each missing prop turns off exactly
 * one control** rather than the dialog refusing to open: no character counter
 * without the bound, no report entry in a comment's menu without both
 * vocabularies. Nothing errors, `vue-tsc` stays clean, and the suite stays
 * green.
 *
 * Reading a thread is public, so this control is identical for a guest — the
 * dialog opens, the composer is replaced by a sign-in line, and no write
 * control renders. It is the one control on the row with no signed-in branch.
 *
 * The visible label is the bare number, because that is what fits; the
 * accessible name says what the number counts and contains the visible text,
 * so speech input still matches it.
 *
 * ## The number is the feed's prop plus a local delta
 *
 * `commentsCount` is a feed prop, and the dialog deliberately reloads no feed
 * props — see `CommentsDialog`'s surface — so a write inside the dialog cannot
 * replace it; it can only be adjusted. That is what the dialog's `posted` and
 * `deleted` emits are for, and this is their listener. `deleted` carries
 * `1 + replies_count` off the deleted row, because deleting a root takes its
 * replies with it in one transaction, and `commentsCount` counts replies too —
 * so both emits are deltas on the same quantity and a post and a delete can
 * interleave freely.
 *
 * The floor is not decoration. The prop is a snapshot from when the feed was
 * rendered, while the dialog shows the thread as it is now, so a reader can
 * delete comments the snapshot never counted — a comment posted by someone
 * else after the feed loaded, or the replies of a root whose `replies_count`
 * outran it. The offset is clamped at `-commentsCount` rather than the
 * rendered number being clamped, so the count can never render below zero
 * *and* a later post shows up immediately instead of being spent climbing out
 * of a negative nobody ever saw.
 */
const { commentsCount, name } = defineProps<{
    petId: number;
    name: string;
    commentsCount: number;
    /** The card's bounded preview; the dialog fetches the real thread. */
    comments?: Comment[];
    canInteract: boolean;
    maxLength?: number | null;
    reportCategories?: SelectOption<ReportCategory>[];
    reportReasons?: SelectOption<ReportReason>[];
}>();

/** Writes made in the open dialog, as a delta on the feed's count. */
const offset = ref(0);

const commentsShown = computed(() => commentsCount + offset.value);

function countPosted(): void {
    offset.value += 1;
}

function countDeleted(removed: number): void {
    offset.value = Math.max(-commentsCount, offset.value - removed);
}

const label = computed(
    () => `${countLabel(commentsShown.value, 'comment')} on ${name}`,
);
</script>

<template>
    <CommentsDialog
        commentable-type="pet"
        :commentable-id="petId"
        :subject="name"
        :comments="comments"
        :can-interact="canInteract"
        :max-length="maxLength"
        :report-categories="reportCategories"
        :report-reasons="reportReasons"
        @posted="countPosted"
        @deleted="countDeleted"
    >
        <Button variant="outline" :aria-label="label">
            <MessageSquare class="size-4" aria-hidden="true" />
            {{ commentsShown }}
        </Button>
    </CommentsDialog>
</template>
