<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ThumbsUp } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import { like as likePet } from '@/routes/pets';

/**
 * Toggle the viewer's like on a listing.
 *
 * One toggle route rather than a like and an unlike, so the button cannot ask
 * for a transition that has already happened. `pets.like` needs a verified
 * account, so a guest is sent to the sign-in page instead of a 403.
 *
 * Drawn as legacy drew it (`components/web/PetCard.vue`, the "Likes with
 * Animation" block): a thumbs-up in a quiet round pill that turns brand-violet
 * and fills when liked. Restyled from the outline heart on the user's
 * instruction (2026-09-06). The classes go through `cn` because `Button`'s
 * ghost variant already sets a hover background and tailwind-merge has to
 * resolve the conflict in favour of the ones written here — see "Shared
 * machinery silently alters your result" in `.ai/rules/general.md`.
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
 * reading the emitted markup, at `petId: 3, likesCount: 10`, **on the outline
 * heart markup this replaced** — the byte counts below are that layout's and
 * are kept as a record of the mechanism, not of today's output. Guest
 * (`canLike: false`): 1133 bytes at 87e21ff, 1112 after, the 21-byte
 * difference being exactly ` aria-pressed="false"` removed from the
 * `<a href="/login">`. Signed in (`canLike: true, isLiked: true`): 1076 bytes,
 * byte-for-byte equal to 87e21ff, `aria-pressed="true"` still on the emitted
 * `<button>` — and `false` there with `isLiked: false`, which is the branch
 * where the attribute means something. The restyle moved classes and the icon
 * only; where each attribute lands is unchanged.
 *
 * The visible label is the bare count, so **every** caller names it from
 * outside with a fall-through `aria-label` — `PetCardActions` on a feed card
 * and `PetDetailHeader` on the listing page, both building the string with
 * `card/labels`' `countLabel`. Repetition is not the reason: an unqualified
 * "10" identifies nothing even where the page renders exactly one of these,
 * which is what the detail page announced while it was the caller that omitted
 * the label. That works here — unlike on `StartConversationButton`, see its
 * docblock — because this component's root is `Button`, which inherits
 * attributes and forwards them through `Primitive` onto the rendered element.
 * Confirmed in the same render: an `aria-label` of `'Like Ruthe, 10 likes'`
 * passed from outside reaches both the guest `<a>` and the signed-in
 * `<button>`, and moving `aria-pressed` did not disturb it.
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
        variant="ghost"
        :class="
            cn(
                'text-muted-foreground hover:bg-muted hover:text-primary-600 dark:hover:text-primary-400 rounded-full',
                isLiked && 'text-primary-600 dark:text-primary-400',
            )
        "
    >
        <Link
            v-if="canLike"
            :href="likePet(petId)"
            as="button"
            preserve-scroll
            preserve-state
            :aria-pressed="isLiked"
        >
            <ThumbsUp
                class="size-5 transition-transform duration-300"
                :class="isLiked ? 'scale-110 fill-current' : ''"
                aria-hidden="true"
            />
            {{ likesCount }}
        </Link>
        <Link v-else :href="login()">
            <ThumbsUp class="size-5" aria-hidden="true" />
            {{ likesCount }}
        </Link>
    </Button>
</template>
