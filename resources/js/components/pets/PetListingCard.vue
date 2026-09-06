<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';
import { computed } from 'vue';
import PetCardActions from '@/components/pets/card/PetCardActions.vue';
import PetCardAttributeIcons from '@/components/pets/card/PetCardAttributeIcons.vue';
import PetCardBadges from '@/components/pets/card/PetCardBadges.vue';
import PetCardDescription from '@/components/pets/card/PetCardDescription.vue';
import PetCardHeader from '@/components/pets/card/PetCardHeader.vue';
import PetCardMedia from '@/components/pets/card/PetCardMedia.vue';
import { useLocale } from '@/composables/useLocale';
import { taxonomyName } from '@/lib/taxonomy';
import { cn } from '@/lib/utils';
import type {
    CommentBounds,
    PetCard,
    ReportCategory,
    ReportReason,
    SelectOption,
} from '@/types';

/**
 * One listing, as a grid tile — drawn as legacy's `components/web/PetCard.vue`
 * drew it, on the user's instruction (2026-09-06): a rounded `<article>` that
 * lifts on hover, an emerald border when the listing is the viewer's own, a
 * tall photo with pills and a hover icon bar over it, then name and gender
 * pill, breed and age, the description with its reveal, a divider, the
 * engagement row and the two gradient buttons. The decomposition into
 * `card/*` components is unchanged; only what each one renders moved.
 *
 * Kept from the previous card and absent from legacy, deliberately: the price
 * beside the name and the distance chip on a nearby feed. They carry
 * information the feed already pays for; parity is the floor here, not a
 * licence to delete them. Not ported: legacy's heart "favourite" button on the
 * photo, whose `toggleFavorite` flipped a local flag and emitted an event
 * nobody listened to — a control that does nothing is not a feature to restore.
 *
 * ## No comment teaser, and every card the same height
 *
 * The previous card ended with the newest comments rendered under the buttons.
 * The user ruled it out (2026-09-06): comments are read in the dialog behind
 * the comment control and nowhere else on the card, as legacy had it. The feed
 * still eager loads the bounded preview, and it is still consumed — the dialog
 * opens on it before fetching the thread — so nothing is paid for silently;
 * `card/PetCardCommentButton` records that contract.
 *
 * The same ruling asked for cards of one size. Everything above the buttons is
 * fixed-height by construction — a 256px photo, a one-line name row, a
 * one-line breed and age row, a description clipped to exactly three lines
 * (`PetCardDescription`) — and the root is a flex column with the action rows
 * pushed to the bottom (`mt-auto`), so the grid's default `stretch` gives every
 * card in a row the same height **and** the same layout inside it: a card
 * whose owner is the viewer (no message button) or whose age is unknown does
 * not pull its buttons up. "Read more" is the one thing that can change a
 * card's height, and only on the reader's own request.
 *
 * The card used to be a single `<Link>` wrapping everything. It is now three
 * discrete targets to the same page — the photo, the name, and an explicit
 * "View details" — each with its own accessible name, which is what let the
 * like, comment, message and share controls land in `PetCardActions` without
 * nesting a button inside an anchor.
 *
 * `canInteract` is derived here, off `auth.user`, exactly as
 * `pages/pets/Show.vue` derives `isSignedIn`. It cannot be a prop: both
 * consumers of this card — `PetFeed.vue` (via `Home.vue`) and
 * `profile/ProfileListings.vue` — pass nothing but `pet`, and neither should
 * have to learn about authentication to render a tile. `auth.user` is null for
 * a guest whatever `types/auth.ts` says, hence the `Boolean`.
 *
 * `overflow-hidden` on the root is not only for the photo's rounded corner. The
 * card is a grid item in both consumers (`PetFeed.vue`,
 * `profile/ProfileListings.vue`), and a grid item's automatic minimum size is
 * its min-content width — except that a box whose `overflow` is not `visible`
 * gets an automatic minimum size of zero instead. `PetCardHeader`'s breed is
 * `truncate`, i.e. `white-space: nowrap`, so it contributes its whole
 * unwrapped width, and so does the name link.
 *
 * The absolute widths are a function of the string being measured, so the
 * invariant is the part worth recording: without `overflow-hidden` the card is
 * sized to that nowrap line's min-content width **plus its horizontal padding
 * and borders**. Measured on the previous layout as +34px — `CardContent`'s
 * `p-4` contributing 16px per side and the border 1px per side. The content
 * box is now `p-5`, so the same arithmetic gives +42px; that figure is derived
 * from the padding change, not re-measured. With `overflow-hidden` the card is
 * the grid track's width instead and the ellipsis applies.
 *
 * Worked example from that measurement, naming its input so it can be re-run:
 * at a 320px viewport, with kind "Golden Retriever" and place "Sheikh Zayed
 * City, Sixth of October Governorate, Arab Republic of Egypt" on the old
 * `kind · place` line, that line's min-content measured 592.625px. With
 * `overflow-hidden` the card measured 320px and the document did not scroll
 * sideways (`documentElement.scrollWidth` 320px); without the class the card
 * measured 626.625px — 592.625 + 34 — and the document did (`scrollWidth`
 * 628px). The same fixture in `rtl`, whose Arabic strings gave a 456.359px
 * line, came out at 490.359px: a different pair, the same +34.
 * Control-measured both ways, in both directions, before this was written
 * down. The place no longer renders on that line (it is in the hover bar's
 * tooltip now), so the nowrap string is shorter today; the mechanism is not.
 *
 * These figures came off the SSR-render fixture described in `.ai/rules/js.md`
 * under "Browser verification" ("Measuring a rendered width off an
 * SSR-rendered card") and only mean something on it: the `resources/css/app.css`
 * manifest key (not the `resources/js/app.ts` one), an explicit
 * `document.fonts.load` per face and weight, and the stylesheet-live and
 * `innerWidth` assertions. Re-run them there before trusting a re-measurement
 * here.
 */
const { pet } = defineProps<{ pet: PetCard }>();

const { locale, tag } = useLocale();

const page = usePage();

/** A signed-in viewer. Every write the action row offers needs one. */
const canInteract = computed(() => Boolean(page.props.auth.user));

/**
 * The three props the card's comments dialog needs, read off the page rather
 * than taken as props — for the same reason `canInteract` is: both consumers
 * (`PetFeed.vue` via `Home.vue`, `profile/ProfileListings.vue`) pass nothing
 * but `pet`, and neither should have to learn what a comment row needs in order
 * to render a tile.
 *
 * They are *page* props, not shared ones, so which page mounted the card is
 * what decides whether they arrive, and `sharedPageProps`' index signature
 * types them `unknown` — hence the casts, which are the narrowest thing that
 * will do and are confined to this component.
 *
 * **Both pages that mount this card ship all three today**, as `pets.show`
 * does for its inline thread (and it renders no card). Established 2026-09-06
 * by reading the `Inertia::render()` payloads of `HomeController::index`,
 * `ProfileController::show` and `PetController::show`, each of which sends
 * `reportCategories`, `reportReasons` and `commentBounds`; not by rendering
 * any of them.
 *
 * The optionality below is therefore a *fallback*, not a description of any
 * current page: each prop that fails to arrive turns off exactly one control —
 * no character counter without the bound, no report entry in a comment's menu
 * without both vocabularies — rather than breaking the dialog. Nothing errors
 * and `vue-tsc` stays clean either way, which is the whole hazard: the three
 * `as ... | undefined` casts here are a widening on server-supplied props, so
 * they silence the one tool that would otherwise catch a controller that
 * stopped sending a key (`.ai/rules/general.md`, "Review what a change REMOVED
 * — and what it merely made optional").
 *
 * Read those three controller keys as live consumers of this component, not as
 * spare payload. `Home` carrying the two report vocabularies repairs a parity
 * regression against legacy and `reportCategories` goes past what legacy ever
 * sent, so parity is the floor here and never a licence to delete: removing one
 * key turns its control off on that page silently.
 */
const commentMaxLength = computed(
    () =>
        (page.props.commentBounds as CommentBounds | undefined)?.max_length ??
        null,
);

const reportCategories = computed(
    () =>
        page.props.reportCategories as
            | SelectOption<ReportCategory>[]
            | undefined,
);

const reportReasons = computed(
    () => page.props.reportReasons as SelectOption<ReportReason>[] | undefined,
);

const place = computed(() =>
    [pet.city, pet.state, pet.country].filter(Boolean).join(', '),
);

/** Only present when the feed query ran with a distance calculation. */
const distance = computed(() =>
    pet.distance === undefined ? null : `${pet.distance} km`,
);

/**
 * `pets.price` is an uncast `decimal` (`DecimalColumn`), so it is a float on
 * SQLite and a string on MySQL. `Number()` is the coercion; passing the raw
 * value happened to work but only because `format()` re-parses a string.
 */
const price = computed(() =>
    pet.price === null
        ? null
        : new Intl.NumberFormat(tag.value, {
              maximumFractionDigits: 2,
          }).format(Number(pet.price)),
);

/**
 * Breed names the listing best; category is the fallback, then a generic.
 *
 * Both go through `taxonomyName`, never `.name` — the resources ship `name` and
 * `name_ar` on every taxonomy row precisely so the client can pick one per
 * locale, and reading `.name` here is what showed Arabic readers English breed
 * names. `locale.current` is the *language*, a different field from
 * `locale.direction`.
 */
const kind = computed(() => {
    const taxon = pet.breed ?? pet.category;

    return taxon ? taxonomyName(taxon, locale.value.current) : 'Pet';
});

/**
 * Legacy's two card frames: emerald for the viewer's own listing, brand-violet
 * on hover for everyone else's. Through `cn` so the variant's border colour
 * replaces the base `border` colour rather than fighting it.
 */
const frame = computed(() =>
    cn(
        'group bg-card text-card-foreground relative flex w-full flex-col overflow-hidden rounded-2xl border shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl',
        pet.is_owner
            ? 'border-emerald-300/70 hover:border-emerald-400/80 hover:shadow-emerald-100 dark:border-emerald-700/60 dark:hover:border-emerald-600/70 dark:hover:shadow-emerald-950/60'
            : 'border-border hover:border-primary-400/50 hover:shadow-primary-100 dark:hover:border-primary-500/50 dark:hover:shadow-primary-900/20',
    ),
);
</script>

<template>
    <article :class="frame">
        <PetCardMedia
            :pet-id="pet.id"
            :name="pet.name"
            :image="pet.image"
            :distance="distance"
            :is-owner="pet.is_owner"
        >
            <PetCardBadges
                :status="pet.status"
                :listing-type="pet.listing_type"
            />

            <PetCardAttributeIcons
                :age="pet.age"
                :gender="pet.gender"
                :vaccinated="pet.vaccinated"
                :spayed-neutered="pet.spayed_neutered"
                :place="place"
            />
        </PetCardMedia>

        <div class="flex flex-1 flex-col gap-4 p-5 pt-4">
            <PetCardHeader
                :pet-id="pet.id"
                :name="pet.name"
                :kind="kind"
                :price="price"
                :gender="pet.gender"
                :age="pet.age"
            />

            <PetCardDescription
                :description="pet.description"
                :name="pet.name"
            />

            <!-- Legacy's patterned divider: a rule with a people glyph on it. -->
            <div class="relative" aria-hidden="true">
                <div class="absolute inset-0 flex items-center">
                    <div class="border-border w-full border-t"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-card text-muted-foreground px-2">
                        <Users class="size-4" />
                    </span>
                </div>
            </div>

            <PetCardActions
                :pet="pet"
                :can-interact="canInteract"
                :comment-max-length="commentMaxLength"
                :report-categories="reportCategories"
                :report-reasons="reportReasons"
                class="mt-auto"
            />
        </div>
    </article>
</template>
