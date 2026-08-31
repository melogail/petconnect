<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FeaturedPhotoPicker from '@/components/pets/form/FeaturedPhotoPicker.vue';
import GalleryPhotoPicker from '@/components/pets/form/GalleryPhotoPicker.vue';
import { petFormErrors, type PetFormState } from '@/lib/petForm';
import type { PetMedia, PetPhotoBounds } from '@/types';

/**
 * The cover photo and the gallery.
 *
 * A create must carry a `featuredImage` — the rule is `required` there and
 * `nullable` on an edit — which is also what makes every create multipart, and
 * therefore what makes the empty-collection trap real for this whole form.
 *
 * `images.*` errors are keyed by index, so the picker is handed the first one
 * rather than a list; the surrounding message names the ceiling.
 */
const { form, photoBounds } = defineProps<{
    form: InertiaForm<PetFormState>;
    photoBounds: PetPhotoBounds;
    /** Attached media rows on an edit; absent on a create. */
    photos?: PetMedia[];
    /** The cover already attached, on an edit. */
    currentFeaturedUrl?: string | null;
}>();

const errors = computed(() => petFormErrors(form.errors));

const galleryError = computed(
    () =>
        errors.value.images ??
        Object.entries(errors.value).find(([key]) =>
            key.startsWith('images.'),
        )?.[1] ??
        errors.value.deletedMediaIds,
);
</script>

<template>
    <div class="space-y-8">
        <section class="space-y-3">
            <div>
                <h3 class="font-medium">Cover photo</h3>
                <p class="text-muted-foreground text-sm">
                    The one photo that represents the listing everywhere.
                </p>
            </div>

            <FeaturedPhotoPicker
                :file="form.featuredImage"
                :current-url="currentFeaturedUrl ?? null"
                :max-kilobytes="photoBounds.max_image_kilobytes"
                :error="errors.featuredImage"
                @update:file="(value) => (form.featuredImage = value)"
            />
        </section>

        <section class="space-y-3">
            <div>
                <h3 class="font-medium">Gallery</h3>
                <p class="text-muted-foreground text-sm">
                    Up to {{ photoBounds.max_gallery_images }} more photos,
                    beside the cover.
                </p>
            </div>

            <GalleryPhotoPicker
                :photos="photos"
                :files="form.images"
                :deleted-ids="form.deletedMediaIds"
                :cap="photoBounds.max_gallery_images"
                :max-kilobytes="photoBounds.max_image_kilobytes"
                :error="galleryError"
                @update:files="(value) => (form.images = value)"
                @update:deleted-ids="(value) => (form.deletedMediaIds = value)"
            />
        </section>
    </div>
</template>
