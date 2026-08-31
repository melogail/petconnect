<script setup lang="ts">
import { ImagePlus, RotateCcw, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { compressListingPhoto, formatBytes } from '@/lib/imageCompression';
import { remainingGallerySlots } from '@/lib/petForm';
import type { PetMedia } from '@/types';

/**
 * The gallery: the photos already attached, the ones being removed, and the
 * new uploads.
 *
 * The cap is enforced server-side over the listing's lifetime as
 * `attached − deleted + uploaded ≤ cap`, with the **cover photo excluded from
 * both sides**, so the picker counts the same way rather than just capping the
 * file input at `cap`. Deleting an existing photo therefore frees a slot
 * immediately, which is what an owner swapping three photos expects.
 *
 * The read and write names differ and both are on this component: `photos` are
 * the attached media rows, `images` are new File uploads, `deletedMediaIds` are
 * ids to detach. A save never posts `photos` back.
 *
 * Picking more files than there are slots keeps the first few and **says so**.
 * Dropping the rest silently is how somebody uploads five photos, sees three,
 * and assumes the other two are still processing.
 */
const {
    photos = [],
    files,
    deletedIds,
    cap,
    maxKilobytes,
} = defineProps<{
    /** Attached media rows, cover photo included — it is filtered out here. */
    photos?: PetMedia[];
    files: File[];
    deletedIds: number[];
    cap: number;
    maxKilobytes: number;
    error?: string;
}>();

const emit = defineEmits<{
    'update:files': [value: File[]];
    'update:deletedIds': [value: number[]];
}>();

const input = ref<HTMLInputElement | null>(null);
const compressing = ref(false);
const previews = ref<string[]>([]);
/** How many of the last pick did not fit, so the message can name a number. */
const overflowed = ref(0);

watch(
    () => files,
    (next) => {
        previews.value.forEach((url) => URL.revokeObjectURL(url));
        previews.value = next.map((file) => URL.createObjectURL(file));
    },
    { immediate: true, deep: true },
);

onBeforeUnmount(() => {
    previews.value.forEach((url) => URL.revokeObjectURL(url));
});

/** The cover photo is not part of the gallery on either side of the sum. */
const attached = computed(() => photos.filter((photo) => !photo.featured));

const remaining = computed(() =>
    remainingGallerySlots(
        cap,
        attached.value.length,
        deletedIds.length,
        files.length,
    ),
);

async function pick(event: Event): Promise<void> {
    const picked = [...((event.target as HTMLInputElement).files ?? [])];

    if (picked.length === 0) {
        return;
    }

    compressing.value = true;
    overflowed.value = Math.max(picked.length - remaining.value, 0);

    try {
        const accepted = picked.slice(0, remaining.value);
        const compressed = await Promise.all(
            accepted.map((file) => compressListingPhoto(file, maxKilobytes)),
        );

        emit('update:files', [...files, ...compressed]);
    } finally {
        compressing.value = false;

        if (input.value) {
            input.value.value = '';
        }
    }
}

function removeFile(index: number): void {
    overflowed.value = 0;

    emit(
        'update:files',
        files.filter((_, position) => position !== index),
    );
}

function toggleAttached(id: number): void {
    overflowed.value = 0;

    emit(
        'update:deletedIds',
        deletedIds.includes(id)
            ? deletedIds.filter((deleted) => deleted !== id)
            : [...deletedIds, id],
    );
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="attached.length > 0 || files.length > 0"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3"
        >
            <div
                v-for="photo in attached"
                :key="photo.id"
                class="relative overflow-hidden rounded-lg"
            >
                <img
                    :src="photo.thumb"
                    :alt="photo.name"
                    class="bg-muted aspect-square w-full object-cover"
                    :class="deletedIds.includes(photo.id) ? 'opacity-30' : ''"
                />
                <Button
                    type="button"
                    variant="secondary"
                    size="icon-sm"
                    class="absolute top-1.5 right-1.5"
                    :aria-label="
                        deletedIds.includes(photo.id)
                            ? 'Keep this photo'
                            : 'Remove this photo'
                    "
                    @click="toggleAttached(photo.id)"
                >
                    <RotateCcw
                        v-if="deletedIds.includes(photo.id)"
                        class="size-3.5"
                    />
                    <X v-else class="size-3.5" />
                </Button>
            </div>

            <div
                v-for="(file, index) in files"
                :key="`${file.name}-${index}`"
                class="relative overflow-hidden rounded-lg"
            >
                <img
                    :src="previews[index]"
                    :alt="file.name"
                    class="bg-muted aspect-square w-full object-cover"
                />
                <Badge variant="secondary" class="absolute bottom-1.5 left-1.5">
                    {{ formatBytes(file.size) }}
                </Badge>
                <Button
                    type="button"
                    variant="secondary"
                    size="icon-sm"
                    class="absolute top-1.5 right-1.5"
                    :aria-label="`Remove ${file.name}`"
                    @click="removeFile(index)"
                >
                    <X class="size-3.5" />
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <input
                ref="input"
                type="file"
                multiple
                :disabled="remaining === 0"
                accept="image/jpeg,image/png,image/gif,image/webp"
                class="text-muted-foreground file:text-foreground text-sm file:mr-3 file:rounded-md file:border file:bg-transparent file:px-3 file:py-1.5 file:text-sm disabled:opacity-50"
                aria-label="Gallery photos"
                @change="pick"
            />
            <span
                v-if="compressing"
                class="text-muted-foreground flex items-center gap-1.5 text-xs"
            >
                <Spinner class="size-3" />
                Compressing…
            </span>
        </div>

        <p class="text-muted-foreground flex items-center gap-1.5 text-xs">
            <ImagePlus class="size-3.5" />
            {{ remaining }} of {{ cap }} slots left, cover photo excluded.
        </p>

        <p
            v-if="overflowed > 0"
            class="text-sm text-amber-700 dark:text-amber-500"
        >
            {{ overflowed }}
            {{ overflowed === 1 ? 'photo was' : 'photos were' }} left out: the
            gallery holds {{ cap }}. Remove one to make room for another.
        </p>

        <InputError :message="error" />
    </div>
</template>
