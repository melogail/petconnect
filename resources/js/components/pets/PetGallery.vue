<script setup lang="ts">
import { ChevronLeft, ChevronRight, PawPrint } from '@lucide/vue';
import emblaCarouselVue from 'embla-carousel-vue';
import { computed, onMounted, ref } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import type { PetMedia } from '@/types';

/**
 * A listing's photos.
 *
 * The cover photo comes first. `photos` already contains it — the media row
 * carries `featured: true` — so `featured_image` is only used when there is no
 * media collection to read at all, which is what a non-owner sees for a listing
 * whose gallery was never eager loaded.
 *
 * ## Four pieces of chrome, because legacy had four
 *
 * Legacy's `components/pet/show/PetGallery.vue` is a hand-rolled
 * `scroll-snap-type: x mandatory` strip with a slide counter, arrows, dots and
 * a thumbnail rail. This is embla driving the same four, which is the same
 * component *choice* — a carousel, not a grid — with a scroll engine that
 * already handles pointer drag, RTL and the `canScrollPrev/Next` states legacy
 * derived by comparing `currentSlide` against `images.length - 1` by hand.
 *
 * Each rule is legacy's, not a preference:
 * - The frame is `rounded-3xl shadow-2xl ring-1`, and each slide is 60% tall
 *   relative to its width (`padding-top: 60%` there, `aspect-5/3` here — the
 *   same ratio, expressed as a ratio).
 * - The counter and the arrows appear only past one photo.
 * - The dots appear only for **2 to 8** photos. Past eight legacy drops them
 *   and leaves the thumbnail rail to carry the position, because sixteen dots
 *   in a row stop reading as an index.
 * - The thumbnail rail appears past one photo and scrolls horizontally.
 *
 * ## RTL is `direction`, not a class
 *
 * Embla is told the reading direction at init (`useLocale().isRtl`, which
 * reads the `locale` shared prop — never a hardcoded `=== 'ar'`), so slide 1
 * is on the right in Arabic and "next" moves right-to-left. The arrows keep
 * their glyphs and swap sides through logical properties (`start-3`/`end-3`),
 * so the chevron always points the way the carousel will actually move.
 * Legacy pinned `left-3`/`right-4` physically and its Arabic gallery ran
 * backwards.
 */
const { photos = [], featuredImage = null } = defineProps<{
    photos?: PetMedia[];
    featuredImage?: string | null;
    name: string;
}>();

const { t } = useTranslations();
const { isRtl } = useLocale();

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

const [emblaRef, emblaApi] = emblaCarouselVue({
    loop: false,
    align: 'start',
    direction: isRtl.value ? 'rtl' : 'ltr',
});

const selected = ref(0);
const canScrollPrev = ref(false);
const canScrollNext = ref(false);

function syncState(): void {
    selected.value = emblaApi.value?.selectedScrollSnap() ?? 0;
    canScrollPrev.value = emblaApi.value?.canScrollPrev() ?? false;
    canScrollNext.value = emblaApi.value?.canScrollNext() ?? false;
}

onMounted(() => {
    emblaApi.value?.on('select', syncState).on('reInit', syncState);
    syncState();
});

function scrollTo(index: number): void {
    emblaApi.value?.scrollTo(index);
}
</script>

<template>
    <div
        v-if="slides.length === 0"
        class="bg-muted flex aspect-5/3 items-center justify-center rounded-3xl"
    >
        <PawPrint class="text-muted-foreground size-12" aria-hidden="true" />
    </div>

    <div v-else>
        <div
            class="relative mb-3 w-full overflow-hidden rounded-3xl shadow-2xl ring-1 ring-black/10 dark:ring-white/10"
        >
            <div ref="emblaRef" class="overflow-hidden rounded-3xl">
                <div class="flex">
                    <div
                        v-for="(slide, index) in slides"
                        :key="slide"
                        class="min-w-0 flex-[0_0_100%]"
                    >
                        <div class="bg-muted relative aspect-5/3">
                            <img
                                :src="slide"
                                :alt="
                                    t('pets.photo_of', {
                                        name,
                                        number: index + 1,
                                    })
                                "
                                class="absolute inset-0 size-full object-cover transition-transform duration-700 hover:scale-105"
                                :loading="index === 0 ? 'eager' : 'lazy'"
                            />
                            <div
                                class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <template v-if="slides.length > 1">
                <div
                    class="absolute end-4 bottom-4 rounded-full bg-black/50 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm"
                >
                    {{ selected + 1 }} / {{ slides.length }}
                </div>

                <button
                    type="button"
                    :disabled="!canScrollPrev"
                    :aria-label="t('pets.previous_photo')"
                    class="absolute start-3 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white disabled:pointer-events-none disabled:opacity-0 dark:bg-black/50 dark:hover:bg-black/70"
                    @click="emblaApi?.scrollPrev()"
                >
                    <ChevronLeft class="size-5 rtl:rotate-180" />
                </button>
                <button
                    type="button"
                    :disabled="!canScrollNext"
                    :aria-label="t('pets.next_photo')"
                    class="absolute end-3 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white disabled:pointer-events-none disabled:opacity-0 dark:bg-black/50 dark:hover:bg-black/70"
                    @click="emblaApi?.scrollNext()"
                >
                    <ChevronRight class="size-5 rtl:rotate-180" />
                </button>
            </template>
        </div>

        <div
            v-if="slides.length > 1 && slides.length <= 8"
            class="mb-2 flex justify-center gap-1.5"
        >
            <button
                v-for="(slide, index) in slides"
                :key="`dot-${slide}`"
                type="button"
                class="h-2 rounded-full transition-all duration-300"
                :class="
                    selected === index
                        ? 'bg-primary w-6'
                        : 'bg-muted-foreground/30 hover:bg-muted-foreground/60 w-2'
                "
                :aria-label="t('pets.go_to_photo', { number: index + 1 })"
                :aria-current="selected === index"
                @click="scrollTo(index)"
            />
        </div>

        <div
            v-if="slides.length > 1"
            class="pet-gallery-thumbnails flex gap-2 overflow-x-auto pb-1"
        >
            <button
                v-for="(slide, index) in slides"
                :key="`thumb-${slide}`"
                type="button"
                class="size-16 shrink-0 overflow-hidden rounded-xl transition-all duration-200"
                :class="
                    selected === index
                        ? 'ring-primary dark:ring-offset-background scale-105 ring-2 ring-offset-2'
                        : 'ring-border/50 hover:ring-border ring-1 hover:scale-105'
                "
                :aria-label="t('pets.go_to_photo', { number: index + 1 })"
                :aria-current="selected === index"
                @click="scrollTo(index)"
            >
                <img
                    :src="slide"
                    alt=""
                    class="size-full object-cover transition-opacity duration-200"
                    :class="
                        selected === index
                            ? 'opacity-100'
                            : 'opacity-60 hover:opacity-100'
                    "
                    loading="lazy"
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
