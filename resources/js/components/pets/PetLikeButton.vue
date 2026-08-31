<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Heart } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { like as likePet } from '@/routes/pets';

/**
 * Toggle the viewer's like on a listing.
 *
 * One toggle route rather than a like and an unlike, so the button cannot ask
 * for a transition that has already happened. `pets.like` needs a verified
 * account, so a guest is sent to the sign-in page instead of a 403.
 */
const { petId, likesCount, isLiked, canLike } = defineProps<{
    petId: number;
    likesCount: number;
    isLiked: boolean;
    /** A signed-in viewer. `false` sends the control to `login` instead. */
    canLike: boolean;
}>();
</script>

<template>
    <Button
        as-child
        :variant="isLiked ? 'default' : 'outline'"
        :aria-pressed="isLiked"
    >
        <Link
            v-if="canLike"
            :href="likePet(petId)"
            as="button"
            preserve-scroll
            preserve-state
        >
            <Heart class="size-4" :class="isLiked ? 'fill-current' : ''" />
            {{ likesCount }}
        </Link>
        <Link v-else :href="login()">
            <Heart class="size-4" />
            {{ likesCount }}
        </Link>
    </Button>
</template>
