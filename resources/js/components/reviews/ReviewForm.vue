<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RatingInput from '@/components/reviews/RatingInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store as storeReview } from '@/routes/reviews';
import type { ReviewBounds, ReviewableType } from '@/types';

/**
 * Leave a review.
 *
 * "You cannot review yourself" arrives as `errors.review` — a flow-level key
 * with no field behind it — so it renders above the fields rather than under
 * one. "You have already reviewed this" no longer needs to: `has_reviewed` on
 * the profile payload means the form is not offered at all in that case.
 */
const { reviewableType, reviewableId, bounds } = defineProps<{
    reviewableType: ReviewableType;
    reviewableId: number;
    /**
     * `petconnect.reviews.min_rate` / `max_rate` / `max_comment_length`, from
     * the page's `reviewBounds` prop. The star widget cannot draw a scale it
     * has not been told the length of, and used to hardcode five.
     */
    bounds: ReviewBounds;
}>();

const rate = ref(bounds.max_rate);
</script>

<template>
    <Form
        v-bind="
            storeReview.form({
                reviewable_type: reviewableType,
                reviewable_id: reviewableId,
            })
        "
        reset-on-success
        :options="{ preserveScroll: true }"
        class="border-border space-y-4 rounded-xl border p-4"
        v-slot="{ errors, processing }"
    >
        <InputError :message="errors.review" />

        <div class="grid gap-2">
            <Label>Your rating</Label>
            <RatingInput
                v-model="rate"
                :min="bounds.min_rate"
                :max="bounds.max_rate"
            />
            <input type="hidden" name="rate" :value="rate" />
            <InputError :message="errors.rate" />
        </div>

        <div class="grid gap-2">
            <Label for="review-comment">Comment (optional)</Label>
            <Textarea
                id="review-comment"
                name="comment"
                rows="3"
                :maxlength="bounds.max_comment_length"
                placeholder="What was it like dealing with this member?"
            />
            <InputError :message="errors.comment" />
        </div>

        <Button type="submit" :disabled="processing">
            <Spinner v-if="processing" />
            Post review
        </Button>
    </Form>
</template>
