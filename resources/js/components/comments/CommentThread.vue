<script setup lang="ts">
import { MessageSquare } from '@lucide/vue';
import CommentComposer from '@/components/comments/CommentComposer.vue';
import CommentComposerGate from '@/components/comments/CommentComposerGate.vue';
import CommentList from '@/components/comments/CommentList.vue';
import { useTranslations } from '@/composables/useTranslations';
import type {
    Comment,
    CommentableType,
    ReportCategory,
    ReportReason,
    SelectOption,
} from '@/types';

/**
 * A commentable's discussion, as the listing page renders it — legacy's
 * `pet/show/PetComments`.
 *
 * The frame is legacy's and is the delta from what this rendered before: a
 * card whose header is a ruled bar carrying a violet speech bubble, the word
 * "Comments" and the total as a pill, with the composer and the thread in a
 * `p-6` body under it. It used to be a bare `<section>` with an `h2` and no
 * card at all, which left it looking like page furniture rather than the last
 * panel of the listing. The card is drawn to the "Aubergine & Lavender" guide
 * (§4): `rounded-[20px] border border-border shadow-card` where legacy had
 * `rounded-2xl` on a half-alpha border with `shadow-sm`; the heading is the
 * card-title scale (Newsreader 21/1.2, weight 500) and the count pill the
 * lavender wash (`bg-secondary text-secondary-foreground`) rather than grey.
 *
 * The composer sits **above** the thread here and **below** it in
 * `CommentsDialog`; that is legacy's split too, and it is the reason the
 * composer is owned by each container rather than by `CommentList`.
 *
 * `commentsCount` is the whole morphMany — roots and replies — and is what the
 * pill shows, because the pill is about comments. `rootCommentsCount` counts
 * top-level comments alone and is what `CommentList` decides "load more" from,
 * because that is what the endpoint pages. The two are equal only on a thread
 * nobody has replied to.
 */
defineProps<{
    comments?: Comment[];
    /** Roots *and* replies — the number the header pill shows. */
    commentsCount: number;
    /** Top-level comments only — the number "load more" is decided from. */
    rootCommentsCount: number;
    commentableType: CommentableType;
    commentableId: number;
    /** `petconnect.comments.thread_per_page` — the *endpoint's* page size. */
    threadPerPage: number;
    /** `petconnect.comments.max_length` — the composer's ceiling. */
    maxLength: number;
    canInteract: boolean;
    reportCategories: SelectOption<ReportCategory>[];
    reportReasons: SelectOption<ReportReason>[];
}>();

const { t } = useTranslations();
</script>

<template>
    <section
        class="border-border bg-card shadow-card overflow-hidden rounded-[20px] border"
    >
        <div
            class="border-border flex items-center justify-between gap-3 border-b px-6 py-4"
        >
            <h2
                class="flex items-center gap-2.5 font-serif text-[21px] leading-[1.2] font-medium tracking-[-0.015em]"
            >
                <MessageSquare class="text-primary size-5" aria-hidden="true" />
                {{ t('comments.comments') }}
            </h2>
            <span
                class="bg-secondary text-secondary-foreground rounded-full px-3 py-0.5 text-[11px] font-semibold"
            >
                {{ t('comments.count', { count: commentsCount }) }}
            </span>
        </div>

        <div class="space-y-6 p-6">
            <CommentComposerGate>
                <CommentComposer
                    :commentable-type="commentableType"
                    :commentable-id="commentableId"
                    :max-length="maxLength"
                />
            </CommentComposerGate>

            <CommentList
                :comments="comments"
                :root-comments-count="rootCommentsCount"
                :commentable-type="commentableType"
                :commentable-id="commentableId"
                :thread-per-page="threadPerPage"
                :max-length="maxLength"
                :can-interact="canInteract"
                :report-categories="reportCategories"
                :report-reasons="reportReasons"
            />
        </div>
    </section>
</template>
