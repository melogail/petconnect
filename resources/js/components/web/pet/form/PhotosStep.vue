<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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

const isMaxImages = computed(() => props.form.images.length >= 3);
</script>

<template>
    <div id="step-3" class="step-container animate-fade-in">
        <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-amber-100/50 dark:border-amber-900/30 hover:border-amber-300 dark:hover:border-amber-700 shadow-lg">
            <!-- Animated Background Gradient -->
            <div class="absolute -z-10 inset-0 bg-gradient-to-br from-amber-50/30 via-yellow-50/20 to-orange-50/10 dark:from-amber-900/20 dark:via-yellow-900/10 dark:to-orange-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
            <!-- Decorative Corner -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-amber-100/20 to-transparent dark:from-amber-900/10 rounded-bl-full opacity-50"></div>
            <CardHeader class="relative z-10">
                <div class="flex items-center space-x-4">
                    <div class="relative p-3 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                        <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                        <Camera class="h-6 w-6 relative z-10" />
                    </div>
                    <div>
                        <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Pet Photos</CardTitle>
                        <CardDescription class="text-gray-500 dark:text-gray-400">Upload a featured photo and up to 3 gallery images</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Featured Image Section -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label is-required class="text-base font-semibold text-gray-800 dark:text-white">Featured Photo</Label>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Main display image</span>
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
                            class="flex aspect-video items-center justify-center rounded-xl border-2 border-dashed border-primary-300 dark:border-primary-700 cursor-pointer hover:border-primary-500 dark:hover:border-primary-500 transition-all bg-primary-50/30 dark:bg-primary-900/10 hover:bg-primary-50/50 dark:hover:bg-primary-900/20"
                        >
                            <div class="text-center p-6">
                                <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                                    <Camera class="w-8 h-8 text-white" />
                                </div>
                                <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Upload Featured Photo</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">This will be the main image for your pet</span>
                            </div>
                        </Label>
                        <div v-else class="relative group">
                            <img
                                :src="form.featuredImagePreview"
                                alt="Featured pet photo"
                                class="w-full aspect-video object-cover rounded-xl border-2 border-primary-200 dark:border-primary-800"
                            />
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-xl flex items-center justify-center">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    @click="emit('removeFeaturedImage')"
                                    class="shadow-lg"
                                >
                                    <X class="w-4 h-4 mr-2" />
                                    Remove
                                </Button>
                            </div>
                            <div class="absolute top-3 left-3 bg-amber-500 text-white px-3 py-1 rounded-full text-xs font-semibold shadow-lg">
                                Featured
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Images Section -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label is-required class="text-base font-semibold text-gray-800 dark:text-white">Gallery Photos</Label>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Up to 3 images</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
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
                                class="flex aspect-square items-center justify-center rounded-lg border-2 border-dashed border-gray-300 cursor-pointer hover:border-gray-400 transition-colors"
                                :class="{ 'opacity-50 cursor-not-allowed': isMaxImages }"
                            >
                                <div class="text-center p-4">
                                    <Camera class="w-8 h-8 mx-auto text-gray-400" />
                                    <span class="mt-2 block text-sm text-gray-600">
                                        {{ form.images.length > 0 ? 'Add more' : 'Add photos' }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ form.images.length }}/3
                                    </span>
                                </div>
                            </Label>
                        </div>

                        <!-- Image previews -->
                        <div
                            v-for="(preview, index) in form.imagePreviews"
                            :key="index"
                            class="relative group"
                        >
                            <img
                                :src="preview"
                                :alt="`Pet photo ${index + 1}`"
                                class="w-full aspect-square object-cover rounded-lg"
                            />
                            <Button
                                type="button"
                                variant="destructive"
                                size="icon"
                                class="absolute -top-2 -right-2 w-6 h-6 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                                @click="emit('removeImage', index)"
                            >
                                <X class="w-3 h-3" />
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
[class*="transition"],
[class*="transform"],
[class*="scale"] {
    will-change: transform;
    transform: translateZ(0);
    -webkit-transform: translateZ(0);
}
</style>
