<script setup lang="ts">
import { Star } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import ReviewCard from '@/components/reviews/ReviewCard.vue';
import ReviewForm from '@/components/reviews/ReviewForm.vue';
import type {
    Paginated,
    ReportCategory,
    ReportReason,
    Review,
    ReviewBounds,
    SelectOption,
} from '@/types';

/**
 * The profile's reviews tab, plus the form for leaving one.
 *
 * `canReview` already folds in `has_reviewed`, which the profile payload now
 * carries: a second review by the same author is refused by a unique index and
 * by `SubmitReview\EnsureNotAlreadyReviewed`, and the page used to offer the
 * form to everybody and explain afterwards through `errors.review`.
 */
defineProps<{
    reviews: Paginated<Review>;
    subjectId: number;
    canReview: boolean;
    /** `petconnect.reviews.*`, from the page's `reviewBounds` prop. */
    bounds: ReviewBounds;
    reportCategories: SelectOption<ReportCategory>[];
    reportReasons: SelectOption<ReportReason>[];
}>();
</script>

<template>
    <section class="space-y-6">
        <ReviewForm
            v-if="canReview"
            reviewable-type="user"
            :reviewable-id="subjectId"
            :bounds="bounds"
        />

        <EmptyState
            v-if="reviews.data.length === 0"
            :icon="Star"
            title="No reviews yet"
            description="Nobody has reviewed this member."
        />

        <template v-else>
            <div>
                <ReviewCard
                    v-for="review in reviews.data"
                    :key="review.id"
                    :review="review"
                    :bounds="bounds"
                    :report-categories="reportCategories"
                    :report-reasons="reportReasons"
                />
            </div>

            <Pagination :links="reviews.meta.links" :only="['reviews']" />
        </template>
    </section>
</template>
