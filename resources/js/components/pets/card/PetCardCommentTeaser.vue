<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { countLabel } from '@/components/pets/card/labels';
import PetCardCommentPreview from '@/components/pets/card/PetCardCommentPreview.vue';
import { show as showPet } from '@/routes/pets';
import type { CommentPreview } from '@/types';

/**
 * The newest few comments on a listing, rendered on the card.
 *
 * The feed already pays for this data: `EagerLoadFeedRelations` loads top-level
 * comments with `user.media`, `withCount(['likes','replies'])`, `withLikedBy`,
 * `withReportedBy` and `latest()`, bounded by
 * `config('petconnect.pets.feed_comment_preview')` — which `ListHomeFeedPets`
 * carries in as `HomeFeedContext::$commentPreview` — and `PetCardResource`
 * ships them as `comments`. Cite the config key, not the number it currently
 * holds: the bound is `env('PETS_FEED_COMMENT_PREVIEW')` with a default of 3,
 * so any literal written here is one deployment away from being false. Until
 * now nothing rendered them, so the query ran on every feed request and the
 * result was discarded. Delete the eager load or render it; do not go back to
 * paying for it silently.
 *
 * Newest first, as the backend's `latest()` ordered them — this is "recent
 * comments", not a thread, and it is not re-sorted into reading order the way
 * `CommentItem` sorts a reply list.
 *
 * `commentsCount` is the true total and counts replies too, so it is almost
 * always larger than `comments.length`; the "view all" link is gated on that
 * difference rather than rendered unconditionally, because when the teaser
 * already shows everything there is, the link only repeats what is on screen —
 * and the comment control in the action row (`PetCardCommentButton`, a dialog
 * trigger rather than a link since the port) reaches the same thread anyway.
 */
const { comments, commentsCount, name } = defineProps<{
    petId: number;
    name: string;
    /** Absent when the feed query did not eager load the preview. */
    comments?: CommentPreview[];
    /** The true total, replies included — not the teaser's length. */
    commentsCount: number;
}>();

const previews = computed(() => comments ?? []);

const hasMore = computed(() => commentsCount > previews.value.length);

const viewAllLabel = computed(
    () => `View all ${countLabel(commentsCount, 'comment')}`,
);
</script>

<template>
    <div v-if="previews.length > 0" class="space-y-2 border-t pt-3">
        <ul class="space-y-2">
            <PetCardCommentPreview
                v-for="comment in previews"
                :key="comment.id"
                :comment="comment"
            />
        </ul>

        <Link
            v-if="hasMore"
            :href="showPet(petId)"
            :aria-label="`${viewAllLabel} on ${name}`"
            class="focus-visible:ring-ring/50 inline-block rounded-sm text-sm font-medium hover:underline focus-visible:ring-[3px] focus-visible:outline-none"
        >
            {{ viewAllLabel }}
        </Link>
    </div>
</template>
