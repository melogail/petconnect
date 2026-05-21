<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import { Camera, X } from 'lucide-vue-next';

interface Props {
    form: any;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    handleFileUpload: [event: Event];
    handleFeaturedImageUpload: [event: Event];
    removeFeaturedImage: [];
    removeImage: [index: number];
}>();

const galleryImageCount = computed(
    () => (props.form.existingImages?.length ?? 0) + props.form.images.length,
);
const isMaxImages = computed(() => galleryImageCount.value >= 3);
</script>

<template>
    <div id="step-3" class="step-container animate-fade-in">
        <Card
            class="group relative overflow-hidden border-2 border-amber-100/50 shadow-lg backdrop-blur-md transition-all duration-500 hover:border-amber-300 hover:shadow-2xl dark:border-amber-900/30 dark:bg-gray-800/70 dark:hover:border-amber-700"
        >
            <!-- Animated Background Gradient -->
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-br from-amber-50/30 via-yellow-50/20 to-orange-50/10 opacity-0 transition-opacity duration-700 group-hover:opacity-100 dark:from-amber-900/20 dark:via-yellow-900/10 dark:to-orange-900/5"
            ></div>
            <!-- Decorative Corner -->
            <div
                class="absolute right-0 top-0 h-32 w-32 rounded-bl-full bg-gradient-to-br from-amber-100/20 to-transparent opacity-50 dark:from-amber-900/10"
            ></div>
            <CardHeader class="relative z-10">
                <div class="flex items-center space-x-4">
                    <div
                        class="relative rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 p-3 text-white shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:shadow-xl"
                    >
                        <div
                            class="absolute inset-0 animate-pulse rounded-2xl bg-white/20"
                        ></div>
                        <Camera class="relative z-10 h-6 w-6" />
                    </div>
                    <div>
                        <CardTitle
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                            >Pet Photos</CardTitle
                        >
                        <CardDescription
                            class="text-gray-500 dark:text-gray-400"
                            >Upload a featured photo and up to 3 gallery
                            images</CardDescription
                        >
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Featured Image Section -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label
                            is-required
                            class="text-base font-semibold text-gray-800 dark:text-white"
                            >Featured Photo</Label
                        >
                        <span class="text-xs text-gray-500 dark:text-gray-400"
                            >Main display image</span
                        >
                    </div>
                    <div class="relative">
                        <input
                            type="file"
                            id="featured-photo"
                            class="hidden"
                            accept="image/*"
                            @change="emit('handleFeaturedImageUpload', $event)"
                        />
                        <Label
                            v-if="!form.featuredImagePreview"
                            for="featured-photo"
                            class="flex aspect-video cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-primary-300 bg-primary-50/30 transition-all hover:border-primary-500 hover:bg-primary-50/50 dark:border-primary-700 dark:bg-primary-900/10 dark:hover:border-primary-500 dark:hover:bg-primary-900/20"
                        >
                            <div class="p-6 text-center">
                                <div
                                    class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-orange-600"
                                >
                                    <Camera class="h-8 w-8 text-white" />
                                </div>
                                <span
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Upload Featured Photo</span
                                >
                                <span
                                    class="mt-1 block text-xs text-gray-500 dark:text-gray-400"
                                    >This will be the main image for your
                                    pet</span
                                >
                            </div>
                        </Label>
                        <div v-else class="group relative">
                            <img
                                :src="form.featuredImagePreview"
                                alt="Featured pet photo"
                                class="aspect-video w-full rounded-xl border-2 border-primary-200 object-cover dark:border-primary-800"
                            />
                            <div
                                class="absolute inset-0 flex items-center justify-center rounded-xl bg-black/50 opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    @click="emit('removeFeaturedImage')"
                                    class="shadow-lg"
                                >
                                    <X class="mr-2 h-4 w-4" />
                                    Remove
                                </Button>
                            </div>
                            <div
                                class="absolute left-3 top-3 rounded-full bg-amber-500 px-3 py-1 text-xs font-semibold text-white shadow-lg"
                            >
                                Featured
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Images Section -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label
                            class="text-base font-semibold text-gray-800 dark:text-white"
                            >Gallery Photos</Label
                        >
                        <span class="text-xs text-gray-500 dark:text-gray-400"
                            >Up to 3 images</span
                        >
                    </div>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <!-- Image upload button -->
                        <div>
                            <input
                                type="file"
                                id="pet-photos"
                                class="hidden"
                                multiple
                                accept="image/*"
                                @change="emit('handleFileUpload', $event)"
                                :disabled="isMaxImages"
                            />
                            <Label
                                for="pet-photos"
                                class="flex aspect-square cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-gray-300 transition-colors hover:border-gray-400"
                                :class="{
                                    'cursor-not-allowed opacity-50':
                                        isMaxImages,
                                }"
                            >
                                <div class="p-4 text-center">
                                    <Camera
                                        class="mx-auto h-8 w-8 text-gray-400"
                                    />
                                    <span
                                        class="mt-2 block text-sm text-gray-600"
                                    >
                                        {{
                                            galleryImageCount > 0
                                                ? 'Add more'
                                                : 'Add photos'
                                        }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ galleryImageCount }}/3
                                    </span>
                                </div>
                            </Label>
                        </div>

                        <!-- Image previews -->
                        <div
                            v-for="(preview, index) in form.imagePreviews"
                            :key="index"
                            class="group relative"
                        >
                            <img
                                :src="preview"
                                :alt="`Pet photo ${index + 1}`"
                                class="aspect-square w-full rounded-lg object-cover"
                            />
                            <Button
                                type="button"
                                variant="destructive"
                                size="icon"
                                class="absolute -right-2 -top-2 h-6 w-6 rounded-full opacity-0 transition-opacity group-hover:opacity-100"
                                @click="emit('removeImage', index)"
                            >
                                <X class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>
                </div>
                <InputError :message="form.errors.images" class="mt-2" />
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
/* Fix text blurriness on hover/scale transforms */
.group:hover {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    transform-style: preserve-3d;
    -webkit-transform-style: preserve-3d;
}

/* Prevent text blur on transform elements */
[class*='transition'],
[class*='transform'],
[class*='scale'] {
    will-change: transform;
    transform: translateZ(0);
    -webkit-transform: translateZ(0);
}
</style>
