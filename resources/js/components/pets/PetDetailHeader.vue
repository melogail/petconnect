<script setup lang="ts">
import { Eye, Heart, MessageSquare } from '@lucide/vue';
import { computed } from 'vue';
import PetLikeButton from '@/components/pets/PetLikeButton.vue';
import PetOwnerActions from '@/components/pets/PetOwnerActions.vue';
import { Badge } from '@/components/ui/badge';
import { useLocale } from '@/composables/useLocale';
import type { PetDetail } from '@/types';

/**
 * The listing's identity and its action bar.
 *
 * `comments_count` is the **true total**, not the length of the bounded
 * `comments` thread beside it, so it is what the counter here reads.
 */
const { pet, canLike } = defineProps<{
    pet: PetDetail;
    /** A signed-in viewer; a guest is sent to sign in instead of a 403. */
    canLike: boolean;
}>();

const { tag } = useLocale();

const price = computed(() =>
    pet.price === null
        ? null
        : new Intl.NumberFormat(tag.value, {
              maximumFractionDigits: 2,
          }).format(pet.price),
);
</script>

<template>
    <header class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 space-y-2">
                <h1 class="text-3xl font-semibold">{{ pet.name }}</h1>

                <div class="flex flex-wrap items-center gap-2">
                    <Badge class="capitalize">{{ pet.listing_type }}</Badge>
                    <Badge
                        :variant="
                            pet.status === 'available' ? 'secondary' : 'outline'
                        "
                        class="capitalize"
                    >
                        {{ pet.status }}
                    </Badge>
                    <span v-if="price" class="text-lg font-semibold">
                        {{ price }}
                    </span>
                </div>

                <div
                    class="text-muted-foreground flex flex-wrap items-center gap-4 text-sm"
                >
                    <span class="flex items-center gap-1.5">
                        <Eye class="size-4" />{{ pet.views }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Heart class="size-4" />{{ pet.likes_count }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <MessageSquare class="size-4" />{{ pet.comments_count }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <PetLikeButton
                    :pet-id="pet.id"
                    :likes-count="pet.likes_count"
                    :is-liked="pet.is_liked"
                    :can-like="canLike"
                />
                <PetOwnerActions
                    v-if="pet.is_owner"
                    :pet-id="pet.id"
                    :status="pet.status"
                />
            </div>
        </div>
    </header>
</template>
