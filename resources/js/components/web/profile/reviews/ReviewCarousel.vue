<script setup lang="ts">
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';
import ReviewCard from './ReviewCard.vue';

const props = defineProps({
    reviews: {
        type: Array,
        required: true,
    },
    currentUser: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['update', 'delete', 'report']);
</script>

<template>
    <div class="relative w-full px-4 sm:px-12">
        <Carousel
            class="w-full"
            :opts="{
                align: 'start',
                loop: false,
            }"
        >
            <CarouselContent class="-ml-4">
                <CarouselItem
                    v-for="review in reviews"
                    :key="review.id"
                    class="pl-4 md:basis-1/2 lg:basis-1/3"
                >
                    <ReviewCard
                        :review="review"
                        :current-user="currentUser"
                        @update="emit('update', $event)"
                        @delete="emit('delete', $event)"
                        @report="emit('report', $event)"
                    />
                </CarouselItem>
            </CarouselContent>
            <CarouselPrevious class="hidden sm:flex" />
            <CarouselNext class="hidden sm:flex" />
        </Carousel>
    </div>
</template>
