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
 *
 * `aria-pressed` sits on the signed-in branch's control, not on the wrapping
 * `Button`. `Button` renders `Primitive` with `as-child`, so anything written
 * there is merged onto whichever branch `v-if`/`v-else` selected — which put
 * `aria-pressed="false"` on the guest `<a href="/login">` as well. The
 * attribute is only defined for `role="button"` (and the other toggle roles),
 * which a plain link does not have, and there is no toggle state to report
 * anyway: a guest has not declined to like this pet, they are being sent to
 * sign in.
 *
 * Established by SSR-rendering both branches through `vue/server-renderer` and
 * reading the emitted markup, at `petId: 3, likesCount: 10`. Guest
 * (`canLike: false`): 1133 bytes at 87e21ff, 1112 now, the 21-byte difference
 * being exactly ` aria-pressed="false"` removed from the `<a href="/login">`.
 * Signed in (`canLike: true, isLiked: true`): 1076 bytes, byte-for-byte equal
 * to 87e21ff, `aria-pressed="true"` still on the emitted `<button>` — and
 * `false` there with `isLiked: false`, which is the branch where the attribute
 * means something.
 *
 * The visible label is the bare count, so callers that render several of these
 * name them from outside with a fall-through `aria-label`. That works here —
 * unlike on `StartConversationButton`, see its docblock — because this
 * component's root is `Button`, which inherits attributes and forwards them
 * through `Primitive` onto the rendered element. Confirmed in the same render:
 * an `aria-label` of `'Like Ruthe, 10 likes'` passed from outside reaches both
 * the guest `<a>` and the signed-in `<button>`, and moving `aria-pressed` did
 * not disturb it.
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
    <Button as-child :variant="isLiked ? 'default' : 'outline'">
        <Link
            v-if="canLike"
            :href="likePet(petId)"
            as="button"
            preserve-scroll
            preserve-state
            :aria-pressed="isLiked"
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
