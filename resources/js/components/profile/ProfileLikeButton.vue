<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Heart } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { like as likeProfile } from '@/routes/profile';

/**
 * Like a member, or take the like back.
 *
 * `profile.like` is the write half the public profile spent two phases without:
 * `ProfileResource` emitted `is_liked` and nothing in the application could
 * flip it. One toggle route, the same `Actions\Likes\ToggleLike` that
 * `pets.like` and `comments.like` run.
 *
 * It needs a verified account, so a guest is sent to the sign-in page rather
 * than into a 403.
 */
const { userId, isLiked, canLike } = defineProps<{
    userId: number;
    isLiked: boolean;
    /** A signed-in viewer who is not the subject. */
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
            :href="likeProfile(userId)"
            as="button"
            preserve-scroll
            preserve-state
        >
            <Heart class="size-4" :class="isLiked ? 'fill-current' : ''" />
            {{ isLiked ? 'Liked' : 'Like' }}
        </Link>
        <Link v-else :href="login()">
            <Heart class="size-4" />
            Like
        </Link>
    </Button>
</template>
