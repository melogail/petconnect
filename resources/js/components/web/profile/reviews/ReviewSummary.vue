<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    reviews: {
        type: Array,
        required: true,
    },
});

const averageRating = computed(() => {
    if (props.reviews.length === 0) return 0;
    const sum = props.reviews.reduce((acc, review) => acc + review.rating, 0);
    return (sum / props.reviews.length).toFixed(1);
});

const ratingCounts = computed(() => {
    const counts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
    props.reviews.forEach((review) => {
        if (counts[review.rating] !== undefined) {
            counts[review.rating]++;
        }
    });
    return counts;
});

const totalReviews = computed(() => props.reviews.length);

const getPercentage = (count) => {
    if (totalReviews.value === 0) return 0;
    return (count / totalReviews.value) * 100;
};
</script>

<template>
    <div
        class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ t('reviews.overall_rating') }}
        </h3>
        <div class="mt-4 flex items-center">
            <div class="flex items-center">
                <Star
                    v-for="i in 5"
                    :key="i"
                    :class="[
                        'h-8 w-8',
                        i <= Math.round(averageRating)
                            ? 'fill-current text-yellow-400'
                            : 'text-gray-300 dark:text-gray-600',
                    ]"
                />
            </div>
            <p class="ms-3 text-2xl font-bold text-gray-900 dark:text-white">
                {{ averageRating }}
            </p>
            <p class="ms-2 text-sm text-gray-500 dark:text-gray-400">
                {{ t('reviews.based_on', { count: totalReviews }) }}
            </p>
        </div>

        <div class="mt-6 space-y-3">
            <div
                v-for="star in [5, 4, 3, 2, 1]"
                :key="star"
                class="flex items-center text-sm"
            >
                <div class="w-12 text-gray-500 dark:text-gray-400">
                    {{ t('reviews.stars', { count: star }) }}
                </div>
                <div class="ms-4 flex-1">
                    <div
                        class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700"
                    >
                        <div
                            class="h-full rounded-full bg-yellow-400"
                            :style="{
                                width: `${getPercentage(ratingCounts[star])}%`,
                            }"
                        ></div>
                    </div>
                </div>
                <div
                    class="ms-4 w-12 text-end text-gray-500 dark:text-gray-400"
                >
                    {{ Math.round(getPercentage(ratingCounts[star])) }}%
                </div>
            </div>
        </div>
    </div>
</template>
