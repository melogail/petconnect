<script setup lang="ts">
import { useAuthUser } from '@/composables/useAuthUser';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import ReviewSummary from './reviews/ReviewSummary.vue';
import ReviewForm from './reviews/ReviewForm.vue';
import ReviewCarousel from './reviews/ReviewCarousel.vue';
import ReportDialog from '@/components/web/ReportDialog.vue';
import Button from '@/components/ui/button/Button.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Plus } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    reviews: {
        type: Array,
        required: true,
    },
    profileOwnerId: {
        type: Number,
        required: true,
    },
    reportReasons: {
        type: Array,
        required: true,
    },
});

const { t } = useTranslations();
const authUser = useAuthUser();
const currentUser = computed(() => authUser.value ?? { id: 0 });

const isAddReviewOpen = ref(false);
const isReportOpen = ref(false);
const reportingReviewId = ref<number | null>(null);

const canAddReview = computed(() => {
    // Authenticated user can add review if they are not the profile owner
    return (
        currentUser.value.id !== 0 &&
        currentUser.value.id !== props.profileOwnerId
    );
});

const handleAddReview = (data) => {
    router.post(
        route('reviews.store', {
            type: 'App\\Models\\User',
            reviewable_type: 'App\\Models\\User',
            reviewable_id: props.profileOwnerId,
        }),
        data,
        {
            onSuccess: () => {
                isAddReviewOpen.value = false;
                toast.success(t('reviews.added_successfully'));
            },
            onError: () => {
                toast.error(t('reviews.failed_to_add'));
            },
        },
    );
};

const handleUpdateReview = (updatedData) => {
    router.put(
        route('reviews.update', { review: updatedData.id }),
        {
            rating: updatedData.rating,
            comment: updatedData.comment,
        },
        {
            onSuccess: () => {
                toast.success(t('reviews.updated_successfully'));
            },
            onError: () => {
                toast.error(t('reviews.failed_to_update'));
            },
        },
    );
};

const handleDeleteReview = (reviewId) => {
    router.delete(route('reviews.destroy', { review: reviewId }), {
        onSuccess: () => {
            toast.success(t('reviews.deleted_successfully'));
        },
        onError: () => {
            toast.error(t('reviews.failed_to_delete'));
        },
    });
};

const handleReportReview = (reviewId) => {
    reportingReviewId.value = reviewId;
    isReportOpen.value = true;
};

const handleSubmitReport = () => {
    isReportOpen.value = false;
};
</script>

<template>
    <div class="space-y-12">
        <!-- Review Summary & Add Review Button -->
        <div
            class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between"
        >
            <div class="flex-1">
                <ReviewSummary :reviews="reviews" />
            </div>

            <!-- Add Review Dialog Trigger -->
            <div class="flex-shrink-0">
                <Dialog v-if="canAddReview" v-model:open="isAddReviewOpen">
                    <DialogTrigger as-child>
                        <Button
                            class="bg-indigo-600 text-white hover:bg-indigo-700"
                        >
                            <Plus class="me-2 h-4 w-4" />
                            {{ t('reviews.add_review') }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-[425px]">
                        <DialogHeader>
                            <DialogTitle>{{
                                t('reviews.write_a_review')
                            }}</DialogTitle>
                            <DialogDescription>
                                {{ t('reviews.share_experience') }}
                            </DialogDescription>
                        </DialogHeader>
                        <ReviewForm @submit="handleAddReview" />
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <!-- Reviews Carousel -->
        <div v-if="reviews.length > 0">
            <h3
                class="mb-6 text-xl font-semibold text-gray-900 dark:text-white"
            >
                {{ t('reviews.what_people_say') }}
            </h3>
            <ReviewCarousel
                :reviews="reviews"
                :current-user="currentUser"
                @update="handleUpdateReview"
                @delete="handleDeleteReview"
                @report="handleReportReview"
            />
        </div>
        <div v-else class="text-center text-gray-500 dark:text-gray-400">
            {{ t('reviews.empty') }}
        </div>

        <!-- Report Dialog -->
        <ReportDialog
            :is-open="isReportOpen"
            :report-reasons="reportReasons"
            :content-id="reportingReviewId || ''"
            reportable-type="App\Models\Review"
            @close="isReportOpen = false"
            @submit="handleSubmitReport"
        />
    </div>
</template>
