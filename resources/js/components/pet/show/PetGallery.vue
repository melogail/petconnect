<script setup lang="ts">
import { ref, computed } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps<{
    images: string[];
    petName: string;
}>();

const carouselRef = ref<HTMLElement | null>(null);
const currentSlide = ref<number>(0);

const goToSlide = (index: number) => {
    const idx = Math.max(0, Math.min(index, props.images.length - 1));
    if (carouselRef.value) {
        carouselRef.value.scrollTo({
            left: carouselRef.value.offsetWidth * idx,
            behavior: 'smooth',
        });
        currentSlide.value = idx;
    }
};

const handleScroll = (event: any) => {
    const scrollPosition = event.target.scrollLeft;
    const itemWidth = event.target.offsetWidth;
    currentSlide.value = Math.round(scrollPosition / itemWidth);
};
</script>

<template>
    <div class="mb-8">
        <!-- Main Carousel -->
        <div
            class="relative mb-3 w-full overflow-hidden rounded-3xl shadow-2xl ring-1 ring-black/10 dark:ring-white/10"
        >
            <div
                ref="carouselRef"
                class="flex overflow-hidden rounded-3xl"
                style="scroll-snap-type: x mandatory"
                @scroll="handleScroll"
            >
                <div
                    v-for="(image, index) in images"
                    :key="index"
                    class="w-full flex-shrink-0"
                    style="scroll-snap-align: start"
                >
                    <div class="bg-muted relative" style="padding-top: 60%">
                        <img
                            :src="image"
                            :alt="`${petName} image ${index + 1}`"
                            class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 hover:scale-105"
                            loading="lazy"
                        />
                        <!-- Subtle overlay gradient -->
                        <div
                            class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent"
                        />
                    </div>
                </div>
            </div>

            <!-- Slide Counter -->
            <div
                v-if="images.length > 1"
                class="absolute bottom-4 right-4 rounded-full bg-black/50 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm"
            >
                {{ currentSlide + 1 }} / {{ images.length }}
            </div>

            <!-- Navigation Arrows -->
            <button
                v-if="images.length > 1"
                @click="goToSlide(currentSlide - 1)"
                :disabled="currentSlide === 0"
                class="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white disabled:pointer-events-none disabled:opacity-0 dark:bg-black/50 dark:hover:bg-black/70"
            >
                <ChevronLeft class="h-5 w-5" />
            </button>
            <button
                v-if="images.length > 1"
                @click="goToSlide(currentSlide + 1)"
                :disabled="currentSlide === images.length - 1"
                class="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white disabled:pointer-events-none disabled:opacity-0 dark:bg-black/50 dark:hover:bg-black/70"
            >
                <ChevronRight class="h-5 w-5" />
            </button>
        </div>

        <!-- Dot Indicators -->
        <div
            v-if="images.length > 1 && images.length <= 8"
            class="mb-2 flex justify-center gap-1.5"
        >
            <button
                v-for="(_, index) in images"
                :key="`dot-${index}`"
                @click="goToSlide(index)"
                class="rounded-full transition-all duration-300"
                :class="
                    currentSlide === index
                        ? 'h-2 w-6 bg-primary'
                        : 'bg-muted-foreground/30 hover:bg-muted-foreground/60 h-2 w-2'
                "
            />
        </div>

        <!-- Thumbnails -->
        <div
            v-if="images.length > 1"
            class="pet-gallery-thumbnails flex gap-2 overflow-x-auto pb-1"
        >
            <button
                v-for="(image, index) in images"
                :key="`thumb-${index}`"
                @click="goToSlide(index)"
                class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl transition-all duration-200"
                :class="{
                    'dark:ring-offset-background scale-105 ring-2 ring-primary ring-offset-2':
                        currentSlide === index,
                    'ring-border/50 hover:ring-border ring-1 hover:scale-105':
                        currentSlide !== index,
                }"
            >
                <img
                    :src="image"
                    :alt="`Thumbnail ${index + 1}`"
                    class="h-full w-full object-cover transition-opacity duration-200"
                    :class="{
                        'opacity-100': currentSlide === index,
                        'opacity-60 hover:opacity-100': currentSlide !== index,
                    }"
                />
            </button>
        </div>
    </div>
</template>

<style scoped>
.pet-gallery-thumbnails::-webkit-scrollbar {
    height: 4px;
}
.pet-gallery-thumbnails::-webkit-scrollbar-track {
    background: var(--muted);
    border-radius: 9999px;
}
.pet-gallery-thumbnails::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 9999px;
}
.pet-gallery-thumbnails::-webkit-scrollbar-thumb:hover {
    background: var(--muted-foreground);
}
</style>
