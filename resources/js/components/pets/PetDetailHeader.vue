<script setup lang="ts">
import { Clock, Eye, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import StartConversationButton from '@/components/messaging/StartConversationButton.vue';
import { countLabel } from '@/components/pets/card/labels';
import PetLikeButton from '@/components/pets/PetLikeButton.vue';
import PetOwnerActions from '@/components/pets/PetOwnerActions.vue';
import { Badge } from '@/components/ui/badge';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { taxonomyName } from '@/lib/taxonomy';
import type { PetDetail } from '@/types';

/**
 * The listing's identity and its action bar — legacy's `PetHeader`.
 *
 * A card of its own, above the facts card, with the violet→blue accent bar
 * legacy puts on the two cards it wants read first (this one and the owner
 * panel). `--primary` is violet-600 here exactly as `primary` was violet in
 * the legacy Tailwind config, so `from-primary via-blue-500 to-primary/60`
 * ports across as written.
 *
 * ## What the badges say
 *
 * Legacy shows the **breed** and the **status**. The category is computed
 * there and then never rendered, so a listing whose breed is null showed no
 * taxonomy at all; the category badge is added here for that case and sits
 * second when both exist. Both names go through `taxonomyName`, never `.name`
 * — the taxonomy resources ship `name` and `name_ar` on every row precisely so
 * the client can pick per locale.
 *
 * The status colours are legacy's, kept as literal green/amber/red rather than
 * moved onto `Badge`'s variants: they are a three-way traffic light and the
 * variant set has no third state. `PetStatus` is a two-value union here
 * (`available` / `unavailable`) where legacy also had `pending` and `adopted`,
 * so only two of the branches can fire — the map is written over the union so
 * a third status added to the enum fails to type-check rather than falling
 * through to grey.
 *
 * ## Guests
 *
 * Every control on the right needs an account. `PetLikeButton` routes a guest
 * to `login` (its own contract, reused not rebuilt); `StartConversationButton`
 * is absent entirely for a guest and for the owner, and `PetOwnerActions` only
 * for the owner. Nothing here can fire an authenticated request for a guest.
 *
 * ## The owner sees the like control, here as on a feed card
 *
 * `PetPolicy::like` is `$user->isVerified()` and carries no owner carve-out, so
 * an owner may like their own listing; `LikeObserver` drops the self-like from
 * the notification recipients, which is where "don't tell them about it" is
 * decided. `PetCardActions` renders `PetLikeButton` for every signed-in viewer
 * including the owner. This header used to nest the like control in the
 * `v-else` of the owner branch, so the one surface that shows the listing in
 * full was the one surface that hid the control — disagreeing with both the
 * policy and the feed, and leaving `PetDetailResource`'s `likes_count` (emitted
 * unconditionally) with no reader for the owner.
 *
 * The owner branch is now `PetOwnerActions` alone: edit/delete are additive,
 * they do not replace the like. `StartConversationButton` stays owner-gated
 * through `canMessage`, which is where "you cannot message yourself" lives.
 *
 * That agreement with the feed is about **who sees the control, and nothing
 * else**. It did not extend to what the control announces: `PetCardActions`
 * named its like button and this header did not, so one button read
 * `Like Luna Belle, 10 likes` on a card and a bare `10` here. Both surfaces
 * name it now — see below — and the scope clause stays because the two
 * components are separate call sites that can drift apart again.
 *
 * ## Both action controls are named from here, and by two different techniques
 *
 * `PetLikeButton`'s visible label is the bare count, so unnamed it announces
 * as an unqualified number. The name is built with the feed's own `countLabel`
 * helper over the same two values (`Like ${name}, ${n} likes`), imported from
 * `card/labels` rather than re-written, so the two surfaces cannot phrase or
 * pluralise it differently. It is passed as a **fall-through `aria-label`**,
 * which reaches the rendered element because that component's root is `Button`
 * → `Primitive`; its docblock records the SSR render that established it.
 *
 * That name is English while everything visible here goes through `t()`, and
 * it is deliberate rather than an oversight: `card/labels` is English-only
 * pending the scheduled i18n pass (its own docblock says not to add
 * `useTranslations` ahead of that pass), and containment still holds in every
 * locale because the visible text is a digit string — "10" is inside
 * "Like Luna Belle, 10 likes" whatever the locale renders around it.
 *
 * `StartConversationButton` takes its name as a **prop**: its root is reka-ui's
 * `DialogRoot`, which drops fall-through attributes in silence, so the
 * technique above produces a still-nameless button there. See its docblock.
 *
 * The string it gets is the feed's `Message {owner} about {pet}`, because an
 * accessible name has to **contain** its trigger's visible text (WCAG 2.5.3 —
 * speech input matches the words a user reads off the screen) and that visible
 * text is a hardcoded English "Message". This header previously passed
 * `pets.contact_owner` → "Contact Owner", which does not contain "Message" and
 * therefore broke the criterion in every locale. It still differs from the
 * `messaging.send_message` → "Send Message" that `PetOwnerCard` passes for the
 * same recipient, which is the distinctness this page needed in the first
 * place: two buttons, two names.
 *
 * Containment is **not** closed for that sibling, and this is not the vertical
 * that can close it: a translated name against an untranslated visible
 * "Message" fails 2.5.3 in Arabic, and the fix is to translate the trigger's
 * visible text, which belongs to `StartConversationButton`'s owner. Recorded
 * in full in its docblock; do not paper over it with another key here.
 *
 * ## `pets.contact_owner` is now a deliberate orphan — do not delete it, do not
 * restore it
 *
 * Dropping it here left it with **no client consumer anywhere**. Verified
 * 2026-09-06 by searching the whole tree rather than this subtree:
 * `grep -rn contact_owner` outside `node_modules`, `vendor` and `public`
 * returns five hits and not one of them is a `t()` call — `lang/en.json:161`,
 * `lang/ar.json:161`, a line of `.ai/rules/lang.md`, and prose in this file and
 * in `pets/PetOwnerCard.vue` explaining why the key stopped being used. Both
 * catalogue entries are still there.
 *
 * This paragraph is the record, and it is here because this is where a
 * `grep contact_owner` lands. An unreferenced key otherwise reads as an
 * oversight to whoever next audits the catalogues, and the two obvious repairs
 * are both wrong:
 *
 * - **Restoring a consumer** would mean putting "Contact Owner" back on a
 *   trigger whose visible text is "Message". That is the 2.5.3 failure this
 *   phase removed, not a repair of it.
 * - **Deleting the key** discards a translated pair — `en` "Contact Owner",
 *   `ar` "التواصل مع المالك" — that becomes usable the moment
 *   `StartConversationButton`'s visible text is translated, which is the
 *   scheduled i18n pass's job and the same pass that owns `lang/*.json`. If
 *   that pass gives the two triggers distinct **visible** strings, this is the
 *   natural name for the header's, and legacy used exactly that word there
 *   (`components/pet/show/PetHeader.vue:182`).
 *
 * So it stays, orphaned on purpose, until the i18n pass decides. `lang/*.json`
 * was not touched from this side.
 *
 * ## Measured, both arms
 *
 * Names read out of Chrome's accessibility tree (`Accessibility.getFullAXTree`
 * over CDP), against a build of this tree served from an isolated copy on a
 * throwaway sqlite database, on `/pets/10` — "Mose", 12 likes, owner
 * "Catharine Zulauf" — 2026-09-06. Signed-in non-owner: the like control
 * computes to `Like Mose, 12 likes` with `pressed: false` intact, the header
 * trigger to `Message Catharine Zulauf about Mose`, the owner card's to
 * `Send Message`. Guest: the like control is the `<a href="/login">` branch and
 * carries the same name, so the fall-through label survives the branch swap.
 *
 * The counterfactual is what makes those readings evidence: the same probe
 * against the same tree with only these two attributes reverted computes
 * `12` for the like control and `Contact Owner` for the header trigger. Under
 * `ar` (`dir="rtl"`), containment measured per trigger:
 * `Message Catharine Zulauf about Mose` contains the visible "Message" and
 * `إرسال الرسالة` does not — the Arabic gap above, observed rather than argued.
 */
const { pet, canLike, canMessage } = defineProps<{
    pet: PetDetail;
    /** A signed-in viewer; a guest is sent to sign in instead of a 403. */
    canLike: boolean;
    /** A signed-in viewer who is not the owner may open a thread. */
    canMessage: boolean;
}>();

const { t } = useTranslations();
const { locale, tag } = useLocale();

const statusClass: Record<PetDetail['status'], string> = {
    available:
        'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    unavailable:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
};

/**
 * A ternary, sitting beside a map written over the whole union — and the two
 * are **not** the same kind of thing, so do not read the one above as licence
 * for the shape of this one.
 *
 * `statusClass` is exhaustive: a third `PetStatus` fails to type-check, which
 * is exactly the tripwire its docblock claims. This ternary has no such
 * protection — a third status would silently read "Unavailable" — and it is
 * kept only because it cannot be reached without `statusClass` above failing to
 * compile first, which is what brings a reader here. Add a status and you fix
 * both, in that order.
 */
const statusLabel = computed(() =>
    pet.status === 'available' ? t('pets.available') : t('pets.unavailable'),
);

/**
 * `PetLikeButton` renders the bare count, so it announces as an unqualified
 * number unless it is named from outside. Same helper and same expression as
 * `PetCardActions`, so the detail page and a feed card announce one listing's
 * like control identically; `aria-pressed` on the control carries the toggle
 * state, so the name does not change with it.
 */
const likeLabel = computed(
    () => `Like ${pet.name}, ${countLabel(pet.likes_count, 'like')}`,
);

/**
 * Names the owner **and** the listing, the way a feed card does. The sibling
 * trigger in `PetOwnerCard` names neither — it stays on "Send Message" — which
 * is what keeps the two apart in a button list.
 *
 * `undefined` where the button is absent, so Vue emits no attribute rather than
 * an empty one; `pet.user` is null-checked here as well as in the template
 * because the payload allows a listing with no owner resource.
 */
const messageLabel = computed(() =>
    pet.user ? `Message ${pet.user.name} about ${pet.name}` : undefined,
);

const breed = computed(() =>
    pet.breed ? taxonomyName(pet.breed, locale.value.current) : null,
);

const category = computed(() =>
    pet.category ? taxonomyName(pet.category, locale.value.current) : null,
);

const gender = computed(() =>
    pet.gender === 'male' ? t('pets.male') : t('pets.female'),
);

const place = computed(() =>
    [pet.location.city, pet.location.state].filter(Boolean).join(', '),
);

/**
 * "1 year" / "3 years" and "1 view" / "429 views".
 *
 * `t()` interpolates and does not select a plural form — it is a flat map
 * lookup, and `lang/*.json` mixes Laravel's `|` plural syntax into a handful of
 * values that nothing on the client resolves — so the branch is here and each
 * count has both keys in both catalogues. `age` is a varchar column that reads
 * numeric, hence the `Number()`; a non-numeric age falls to the plural form,
 * which is the safer of the two to be wrong about.
 */
const age = computed(() =>
    t(Number(pet.age) === 1 ? 'pets.age_year' : 'pets.age_years', {
        count: pet.age,
    }),
);

/** `views` is a plain integer column; the grouping separator is the locale's. */
const views = computed(() =>
    t(pet.views === 1 ? 'pets.view_count' : 'pets.views_count', {
        count: new Intl.NumberFormat(tag.value).format(pet.views),
    }),
);
</script>

<template>
    <header
        class="border-border/50 bg-card mb-6 overflow-hidden rounded-2xl border shadow-sm"
    >
        <div
            class="from-primary to-primary/60 h-1.5 bg-gradient-to-r via-blue-500"
        />

        <div class="p-6">
            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="min-w-0 flex-1">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h1
                            class="text-foreground text-3xl font-bold tracking-tight"
                        >
                            {{ pet.name }}
                        </h1>
                        <Badge
                            v-if="breed"
                            variant="secondary"
                            class="px-3 text-xs font-medium"
                        >
                            {{ breed }}
                        </Badge>
                        <Badge
                            v-if="category"
                            variant="outline"
                            class="px-3 text-xs font-medium"
                        >
                            {{ category }}
                        </Badge>
                        <Badge
                            class="border-transparent px-3 text-xs font-medium"
                            :class="statusClass[pet.status]"
                        >
                            {{ statusLabel }}
                        </Badge>
                    </div>

                    <div
                        class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                    >
                        <span v-if="pet.age" class="flex items-center gap-1.5">
                            <Clock
                                class="text-primary/70 size-4"
                                aria-hidden="true"
                            />
                            {{ age }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span
                                class="bg-muted-foreground/40 size-1 rounded-full"
                                aria-hidden="true"
                            />
                            {{ gender }}
                        </span>
                        <span v-if="place" class="flex items-center gap-1.5">
                            <MapPin
                                class="text-primary/70 size-4"
                                aria-hidden="true"
                            />
                            {{ place }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <Eye
                                class="text-primary/70 size-4"
                                aria-hidden="true"
                            />
                            {{ views }}
                        </span>
                    </div>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <PetOwnerActions
                        v-if="pet.is_owner"
                        :pet-id="pet.id"
                        :status="pet.status"
                    />
                    <PetLikeButton
                        :pet-id="pet.id"
                        :likes-count="pet.likes_count"
                        :is-liked="pet.is_liked"
                        :can-like="canLike"
                        :aria-label="likeLabel"
                    />
                    <StartConversationButton
                        v-if="canMessage && pet.user"
                        :recipient-id="pet.user.id"
                        :recipient-name="pet.user.name"
                        :trigger-label="messageLabel"
                    />
                </div>
            </div>
        </div>
    </header>
</template>
