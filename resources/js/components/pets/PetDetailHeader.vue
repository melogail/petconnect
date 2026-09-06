<script setup lang="ts">
import { Clock, Eye, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import StartConversationButton from '@/components/messaging/StartConversationButton.vue';
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

const statusLabel = computed(() =>
    pet.status === 'available' ? t('pets.available') : t('pets.unavailable'),
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
                    />
                    <StartConversationButton
                        v-if="canMessage && pet.user"
                        :recipient-id="pet.user.id"
                        :recipient-name="pet.user.name"
                        :trigger-label="t('pets.contact_owner')"
                    />
                </div>
            </div>
        </div>
    </header>
</template>
