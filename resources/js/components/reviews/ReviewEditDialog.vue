<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RatingInput from '@/components/reviews/RatingInput.vue';
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
import { update as updateReview } from '@/routes/reviews';
import type { Review, ReviewBounds } from '@/types';

/**
 * Rewrite a review.
 *
 * `reviews.update` is a replacement — its `comment` rule carries `present` — so
 * the textarea is always in the DOM and always submitted, even when emptied.
 */
const { review, bounds } = defineProps<{
    review: Review;
    /** `petconnect.reviews.*`, shipped as the page's `reviewBounds` prop. */
    bounds: ReviewBounds;
}>();

const open = ref(false);
const rate = ref(review.rate);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="sm" class="text-muted-foreground">
                <Pencil class="size-4" />
                Edit
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>Edit your review</DialogTitle>
                <DialogDescription>
                    Both the rating and the comment are replaced.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="updateReview.form(review.id)"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <InputError :message="errors.review" />

                <div class="grid gap-2">
                    <Label>Rating</Label>
                    <RatingInput
                        v-model="rate"
                        :min="bounds.min_rate"
                        :max="bounds.max_rate"
                    />
                    <input type="hidden" name="rate" :value="rate" />
                    <InputError :message="errors.rate" />
                </div>

                <div class="grid gap-2">
                    <Label for="review-comment-edit">Comment</Label>
                    <Textarea
                        id="review-comment-edit"
                        name="comment"
                        rows="4"
                        :maxlength="bounds.max_comment_length"
                        :default-value="review.comment ?? ''"
                    />
                    <InputError :message="errors.comment" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save review
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
