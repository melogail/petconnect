<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import StartConversationButton from '@/components/messaging/StartConversationButton.vue';
import { Button } from '@/components/ui/button';
import UserAvatar from '@/components/UserAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import { show as showProfile } from '@/routes/profile';
import type { PetOwner } from '@/types';

/**
 * Who published the listing — legacy's `PetOwnerCard`, top of the sticky
 * sidebar.
 *
 * The frame is legacy's: `rounded-2xl` with the same violet→blue accent bar
 * `PetDetailHeader` carries, a 64px avatar, the name as a link to the profile,
 * the owner's location, and the two calls to action side by side in a
 * `grid-cols-2` when both are there.
 *
 * ## Five of legacy's elements are deliberately absent
 *
 * Each was invented in the template rather than read from the payload, and
 * reproducing invented data is the one thing the port is explicitly not for:
 *
 * - **A green presence dot.** Nothing in the schema tracks whether a user is
 *   online, so the dot was green for everybody, always.
 * - **A blue verified check.** `verified: true` was a literal in legacy's
 *   `owner` computed, not a column.
 * - **A five-star rating.** Legacy read `user.rating` and defaulted it to
 *   `5.0`, so an owner nobody has reviewed showed a perfect score.
 *   `PetOwnerResource` emits no rating at all — see the report; this needs a
 *   backend key before it can come back honestly.
 * - **"Member since 2023".** Read from `user.created_at` with the year `2023`
 *   hardcoded as the fallback. `PetOwnerResource` emits no `created_at`.
 * - **"Response rate 98% / Response time: within an hour"** and a **"Call Now"**
 *   button. The two figures are literals in the markup, and the button emitted
 *   a `call` event that neither of its two parents listened for — pressing it
 *   did nothing at all.
 *
 * ## Two of these accessible names on one page
 *
 * `pages/pets/Show.vue` mounts a `StartConversationButton` here **and** in
 * `PetDetailHeader`, for the same recipient and under the same condition, so a
 * screen reader's button list held two entries reading "Contact Owner" with
 * nothing to tell them apart — the defect already fixed for the feed's
 * identical "Message" buttons, and the reason `triggerLabel` exists at all (see
 * that component's docblock: its root is reka-ui's `DialogRoot`, so a
 * fall-through `aria-label` is dropped in silence).
 *
 * Legacy differentiated the pair in its *visible* text:
 * `components/pet/show/PetHeader.vue:182` reads "Contact Owner",
 * `components/pet/show/PetOwnerCard.vue:149` reads "Message". Here both
 * triggers read "Message" — that string is hardcoded inside
 * `StartConversationButton` — so the differentiation lands on the accessible
 * name instead, and the two must still be distinct from each other.
 *
 * This one takes `messaging.send_message` → "Send Message", an existing key
 * present in both `lang/en.json` and `lang/ar.json` — no key was added. The
 * header no longer takes `pets.contact_owner`: an accessible name has to
 * **contain** its trigger's visible text (WCAG 2.5.3), "Contact Owner" does not
 * contain "Message", and it now builds the feed's `Message {owner} about {pet}`
 * instead. "Send Message" does contain it, so this trigger satisfies the
 * criterion **in English**.
 *
 * In Arabic it does not, and that is written down rather than fixed:
 * `messaging.send_message` is "إرسال الرسالة" against a visible "Message". No
 * change here can close it — the trigger's visible text has to be translated,
 * which is `StartConversationButton`'s to do (see its docblock, and the
 * per-locale check `.ai/rules/lang.md` puts on the scheduled i18n pass). Do not
 * swap this key for an English-shaped one to make the containment check pass.
 *
 * ## The avatar keeps this app's treatment, not legacy's
 *
 * `UserAvatar` is the one avatar component in the application — the comment
 * thread, the messaging inbox and the profile header all render through it — so
 * its neutral initials fallback is used here instead of legacy's violet→blue
 * gradient one. Diverging would make the same person look like two different
 * people on two surfaces a reader moves between in one click.
 */
const { owner, canMessage } = defineProps<{
    owner: PetOwner;
    /** A signed-in viewer who is not the owner may open a thread. */
    canMessage: boolean;
}>();

const { t } = useTranslations();
</script>

<template>
    <div
        class="border-border/50 bg-card overflow-hidden rounded-2xl border shadow-sm"
    >
        <div
            class="from-primary to-primary/60 h-1.5 bg-gradient-to-r via-blue-500"
        />

        <div class="p-6">
            <div class="mb-5 flex items-start gap-4">
                <Link :href="showProfile(owner.id)" class="shrink-0">
                    <UserAvatar
                        :name="owner.name"
                        :avatar="owner.avatar"
                        class="size-16 border-2 border-white shadow-md dark:border-gray-800"
                    />
                </Link>

                <div class="min-w-0 flex-1">
                    <h2 class="mb-1 truncate text-lg font-bold">
                        <Link
                            :href="showProfile(owner.id)"
                            class="hover:text-primary transition-colors duration-300 ease-out"
                        >
                            {{ owner.name }}
                        </Link>
                    </h2>

                    <p
                        class="text-muted-foreground flex items-center gap-1.5 text-sm"
                    >
                        <MapPin
                            class="size-3.5 shrink-0 text-blue-400"
                            aria-hidden="true"
                        />
                        <span class="truncate">
                            {{ owner.location || t('pets.location_unknown') }}
                        </span>
                    </p>
                </div>
            </div>

            <div
                class="grid gap-2.5"
                :class="canMessage ? 'grid-cols-2' : 'grid-cols-1'"
            >
                <StartConversationButton
                    v-if="canMessage"
                    :recipient-id="owner.id"
                    :recipient-name="owner.name"
                    :trigger-label="t('messaging.send_message')"
                />
                <Button
                    as-child
                    variant="outline"
                    class="border-primary/20 hover:border-primary/50 hover:bg-primary/5 h-11 rounded-xl border-2 font-semibold transition-all duration-200"
                >
                    <Link :href="showProfile(owner.id)">
                        {{ t('pets.view_profile') }}
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
