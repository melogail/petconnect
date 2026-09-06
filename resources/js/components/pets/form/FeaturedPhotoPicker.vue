<script setup lang="ts">
import { Camera, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { compressListingPhoto, formatBytes } from '@/lib/imageCompression';

/**
 * The cover photo.
 *
 * `featuredImage` is the **write** name and is a File; `featured_image` on the
 * read payload is a URL and is never posted back. On an edit, leaving this
 * empty keeps the photo already attached, which is why the current one is
 * shown as `currentUrl` rather than seeded into the file input.
 *
 * The picked file is compressed to fit `maxKilobytes` before it goes anywhere
 * near the form. That number is the server's own `max:` ceiling, shipped as
 * `photoBounds.max_image_kilobytes` — it is under what any phone camera
 * produces, so without this the most likely outcome of this field is a 422
 * after a full upload.
 *
 * A GIF is the exception at both ends: `accept` allows one because the
 * validator does, and `compressListingPhoto` leaves it alone because
 * re-encoding one through a canvas would throw the animation away. An
 * oversized GIF is refused by the server, so the hint says so.
 */
const {
    file = null,
    currentUrl = null,
    maxKilobytes,
} = defineProps<{
    file?: File | null;
    /** The photo already attached, on an edit. */
    currentUrl?: string | null;
    maxKilobytes: number;
    error?: string;
}>();

const emit = defineEmits<{ 'update:file': [value: File | null] }>();

const input = ref<HTMLInputElement | null>(null);
const compressing = ref(false);
const preview = ref<string | null>(null);

watch(
    () => file,
    (next) => {
        if (preview.value !== null) {
            URL.revokeObjectURL(preview.value);
        }

        preview.value = next === null ? null : URL.createObjectURL(next);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (preview.value !== null) {
        URL.revokeObjectURL(preview.value);
    }
});

const shown = computed(() => preview.value ?? currentUrl);

async function pick(event: Event): Promise<void> {
    const picked = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (picked === null) {
        return;
    }

    compressing.value = true;

    try {
        emit('update:file', await compressListingPhoto(picked, maxKilobytes));
    } finally {
        compressing.value = false;
    }
}

function clear(): void {
    emit('update:file', null);

    if (input.value) {
        input.value.value = '';
    }
}
</script>

<template>
    <div class="space-y-2">
        <div
            class="border-border bg-muted/40 relative flex aspect-16/9 items-center justify-center overflow-hidden rounded-xl border border-dashed"
        >
            <img
                v-if="shown"
                :src="shown"
                alt="Cover photo"
                class="size-full object-cover"
            />
            <div
                v-else
                class="text-muted-foreground flex flex-col items-center gap-2 text-sm"
            >
                <Camera class="size-8" />
                No cover photo yet
            </div>

            <Button
                v-if="file"
                type="button"
                variant="secondary"
                size="icon"
                class="absolute top-2 right-2"
                aria-label="Remove cover photo"
                @click="clear"
            >
                <X class="size-4" />
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <input
                ref="input"
                type="file"
                accept="image/jpeg,image/png,image/gif,image/webp"
                class="text-muted-foreground file:text-foreground text-sm file:mr-3 file:rounded-md file:border file:bg-transparent file:px-3 file:py-1.5 file:text-sm"
                aria-label="Cover photo"
                @change="pick"
            />
            <span
                v-if="compressing"
                class="text-muted-foreground flex items-center gap-1.5 text-xs"
            >
                <Spinner status class="size-3" />
                Compressing…
            </span>
            <span v-else-if="file" class="text-muted-foreground text-xs">
                {{ formatBytes(file.size) }}
            </span>
        </div>

        <p class="text-muted-foreground text-xs">
            JPEG, PNG, GIF or WebP, up to {{ maxKilobytes }} KB. A large JPEG,
            PNG or WebP is shrunk automatically; a GIF is kept as it is, so an
            oversized one has to be shrunk before it is picked.
        </p>

        <InputError :message="error" />
    </div>
</template>
