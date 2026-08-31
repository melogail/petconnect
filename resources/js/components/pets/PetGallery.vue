<script setup lang="ts">
import { ChevronLeft, ChevronRight, PawPrint } from '@lucide/vue';
import emblaCarouselVue from 'embla-carousel-vue';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { PetMedia } from '@/types';

/**
 * A listing's photos.
 *
 * The cover photo comes first. `photos` already contains it — the media row
 * carries `featured: true` — so `featured_image` is only used when there is no
 * media collection to read at all, which is what a non-owner sees for a listing
 * whose gallery was never eager loaded.
 */
const { photos = [], featuredImage = null } = defineProps<{
    photos?: PetMedia[];
    featuredImage?: string | null;
    name: string;
}>();

const slides = computed<string[]>(() => {
    if (photos.length > 0) {
        return [...photos]
            .sort(
                (a, b) =>
                    Number(b.featured ?? false) - Number(a.featured ?? false),
            )
            .map((photo) => photo.display);
    }

    return featuredImage === null ? [] : [featuredImage];
});

const [emblaRef, emblaApi] = emblaCarouselVue({ loop: false, align: 'start' });

const selected = ref(0);

onMounted(() => {
    emblaApi.value?.on('select', () => {
        selected.value = emblaApi.value?.selectedScrollSnap() ?? 0;
    });
});

function scrollTo(index: number): void {
    emblaApi.value?.scrollTo(index);
}
</script>

<template>
    <div
        v-if="slides.length === 0"
        class="bg-muted flex aspect-16/10 items-center justify-center rounded-xl"
    >
        <PawPrint class="text-muted-foreground size-12" />
    </div>

    <div v-else class="relative">
        <div ref="emblaRef" class="overflow-hidden rounded-xl">
            <div class="flex">
                <div
                    v-for="(slide, index) in slides"
                    :key="slide"
                    class="min-w-0 flex-[0_0_100%]"
                >
                    <img
                        :src="slide"
                        :alt="`${name} — photo ${index + 1}`"
                        class="bg-muted aspect-16/10 w-full object-cover"
                        :loading="index === 0 ? 'eager' : 'lazy'"
                    />
                </div>
            </div>
        </div>

        <template v-if="slides.length > 1">
            <Button
                variant="secondary"
                size="icon"
                class="absolute top-1/2 left-3 -translate-y-1/2 rounded-full"
                aria-label="Previous photo"
                @click="emblaApi?.scrollPrev()"
            >
                <ChevronLeft class="size-4" />
            </Button>
            <Button
                variant="secondary"
                size="icon"
                class="absolute top-1/2 right-3 -translate-y-1/2 rounded-full"
                aria-label="Next photo"
                @click="emblaApi?.scrollNext()"
            >
                <ChevronRight class="size-4" />
            </Button>

            <div
                class="absolute inset-x-0 bottom-3 flex items-center justify-center gap-1.5"
            >
                <button
                    v-for="(slide, index) in slides"
                    :key="slide"
                    type="button"
                    class="size-2 rounded-full transition-colors"
                    :class="
                        index === selected
                            ? 'bg-background'
                            : 'bg-background/50'
                    "
                    :aria-label="`Go to photo ${index + 1}`"
                    :aria-current="index === selected"
                    @click="scrollTo(index)"
                />
            </div>
        </template>
    </div>
</template>
