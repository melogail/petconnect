<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import StartConversationButton from '@/components/messaging/StartConversationButton.vue';
import { countLabel } from '@/components/pets/card/labels';
import PetCardCommentLink from '@/components/pets/card/PetCardCommentLink.vue';
import PetCardShareMenu from '@/components/pets/card/PetCardShareMenu.vue';
import PetLikeButton from '@/components/pets/PetLikeButton.vue';
import { Button } from '@/components/ui/button';
import { show as showPet } from '@/routes/pets';
import type { PetCard } from '@/types';

/**
 * The card's action row.
 *
 * It exists as its own component because none of these could have lived inside
 * the anchor that used to wrap the whole card: a button nested in an anchor is
 * invalid HTML and breaks keyboard and screen-reader behaviour while looking
 * fine with a mouse. Every control here is a sibling of the card's three links,
 * never a descendant of one.
 *
 * ## Guests
 *
 * Every write route behind this row is `auth` + `verified` and rate-limited, so
 * a control that fires for a guest earns a 403, not a sign-in prompt. Each one
 * therefore either routes to `login` or is absent:
 *
 * - **Like** — `PetLikeButton` swaps `pets.like` for `login()` when `canLike`
 *   is false. That is the component's own contract; it is reused, not rebuilt.
 * - **Comment** — `pets.show` is public, so it is unchanged for a guest.
 * - **Message** — absent. `StartConversationButton` is rendered only for a
 *   signed-in viewer who is not the owner.
 * - **Share** — external destinations and the clipboard only; no account.
 * - **View details** — `pets.show` again, public.
 *
 * `canInteract` is derived once in `PetListingCard` off `auth.user`, the way
 * `pages/pets/Show.vue` does it, because both of this card's consumers
 * (`PetFeed` and `profile/ProfileListings`) pass nothing but `pet`.
 *
 * Hiding the message button from the owner is a client-side derivation off
 * `is_owner`, a prop a cached or prefetched page can serve stale, so it is the
 * server's `Rule::notIn` on `recipient_id` that actually decides — see that
 * component's own note. Both existing call sites guard the same way; this is
 * matching the convention, not relying on it.
 *
 * ## Naming
 *
 * Every `aria-label` built here **extends** the visible text rather than
 * replacing it — the visible characters stay a substring of the accessible
 * name ("10" inside "Like Ruthe, 10 likes") — because speech input matches the
 * words a user reads off the screen, and a label that drops them breaks it
 * silently, with nothing visibly wrong.
 *
 * ## Two rows, not one
 *
 * The engagement controls and the two navigational ones are separate flex rows
 * rather than one wrapping row, so the wrap point is a deliberate grouping
 * instead of a function of how long the owner's name happens to be. Tab order
 * follows the same grouping.
 *
 * The height is deliberately uniform. Nothing on either row asks for a smaller
 * size — four controls take the default `Button` size (`h-9`) and the share
 * trigger takes `size="icon"` (`size-9`), which is square at that same height —
 * so all five render at 36px, measured on the SSR-rendered card at a 320px
 * viewport. That is a behavioural change from 2a, where "View details" alone
 * carried `size="sm"` (`h-8`) and sat 4px shorter than its neighbours.
 */
const { pet, canInteract } = defineProps<{
    pet: PetCard;
    /** A signed-in viewer. Every write on this row needs a verified account. */
    canInteract: boolean;
}>();

/** The owner, only when there is somebody signed in who is not them. */
const owner = computed(() =>
    canInteract && !pet.is_owner ? (pet.user ?? null) : null,
);

/**
 * `PetLikeButton`'s visible label is the bare count, which announces as an
 * unqualified "12" beside the comment control's "4". The name is supplied from
 * here as a fall-through attribute rather than by editing that component, which
 * the listing page shares; `aria-pressed` on it already carries the toggle
 * state, so the label stays the same in both.
 */
const likeLabel = computed(
    () => `Like ${pet.name}, ${countLabel(pet.likes_count, 'like')}`,
);

/**
 * `StartConversationButton` reads "Message" on every card, so a screen
 * reader's button list is one entry per listing with nothing to tell them
 * apart. Both the owner and the listing are named, because either alone is
 * ambiguous on a feed: one owner can have several listings, and two owners can
 * have listings with the same pet name.
 *
 * Passed as a prop and not as a fall-through `aria-label`. That component's
 * root is reka-ui's `DialogRoot`, which renders no element and drops attributes
 * silently — the technique that works on `PetLikeButton` above produces a
 * still-nameless button here. See its docblock, which records the measurement.
 *
 * The change is confined to that one attribute, established by SSR-rendering
 * this component through `vue/server-renderer` against the same render of the
 * file at `HEAD` (pet `Luna`, owner `Ruthe`, `likes_count` 10,
 * `comments_count` 4). Signed-in non-owner: 6128 → 6166 bytes, and the whole
 * 38-byte insertion is ` aria-label="Message Ruthe about Luna"` on the
 * `aria-haspopup="dialog"` trigger. Guest (4954 bytes) and owner (4985 bytes)
 * are byte-identical, which is the point: `messageLabel` is `undefined`
 * wherever the button is absent, so Vue emits no attribute at all rather than
 * an empty one.
 */
const messageLabel = computed(() =>
    owner.value ? `Message ${owner.value.name} about ${pet.name}` : undefined,
);
</script>

<template>
    <div class="space-y-2 pt-1">
        <div class="flex flex-wrap items-center gap-2">
            <PetLikeButton
                :pet-id="pet.id"
                :likes-count="pet.likes_count"
                :is-liked="pet.is_liked"
                :can-like="canInteract"
                :aria-label="likeLabel"
            />

            <PetCardCommentLink
                :pet-id="pet.id"
                :name="pet.name"
                :comments-count="pet.comments_count"
            />

            <PetCardShareMenu :pet-id="pet.id" :name="pet.name" />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <StartConversationButton
                v-if="owner"
                :recipient-id="owner.id"
                :recipient-name="owner.name"
                :trigger-label="messageLabel"
            />

            <Button as-child variant="outline">
                <Link
                    :href="showPet(pet.id)"
                    :aria-label="`View details for ${pet.name}`"
                >
                    View details
                    <ArrowRight
                        class="size-4 rtl:rotate-180"
                        aria-hidden="true"
                    />
                </Link>
            </Button>
        </div>
    </div>
</template>
