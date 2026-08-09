<script setup lang="ts">
import { ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { Star } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import Label from '@/components/ui/label/Label.vue';

const { t } = useTranslations();

const props = defineProps({
    initialRating: {
        type: Number,
        default: 0,
    },
    initialComment: {
        type: String,
        default: '',
    },
    action: {
        type: String,
        default: '#',
    },
});

const emit = defineEmits(['submit']);

const rating = ref(props.initialRating);
const comment = ref(props.initialComment);
const hoverRating = ref(0);

const submit = () => {
    emit('submit', { rating: rating.value, comment: comment.value });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
            <Label>{{ t('reviews.rating') }}</Label>
            <div class="flex items-center space-x-1">
                <button
                    v-for="i in 5"
                    :key="i"
                    type="button"
                    @mouseenter="hoverRating = i"
                    @mouseleave="hoverRating = 0"
                    @click="rating = i"
                    class="focus:outline-none"
                    :class="{
                        'scale-110 transition-transform': hoverRating === i,
                    }"
                >
                    <Star
                        class="h-6 w-6 transition-colors"
                        :class="[
                            i <= (hoverRating || rating)
                                ? 'fill-current text-yellow-400'
                                : 'text-gray-300 dark:text-gray-600',
                        ]"
                    />
                </button>
            </div>
        </div>

        <div class="space-y-2">
            <Label for="comment">{{ t('reviews.review') }}</Label>
            <Textarea
                id="comment"
                v-model="comment"
                :placeholder="t('reviews.share_experience_placeholder')"
                rows="4"
                class="w-full resize-none"
            />
        </div>

        <div class="flex justify-end">
            <Button
                type="submit"
                :disabled="rating === 0 || !comment.trim()"
                class="bg-indigo-600 text-white hover:bg-indigo-700"
            >
                {{ t('reviews.submit_review') }}
            </Button>
        </div>
    </form>
</template>
