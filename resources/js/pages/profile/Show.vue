<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CreatePetButton from '@/components/pets/CreatePetButton.vue';
import ProfileHeader from '@/components/profile/ProfileHeader.vue';
import ProfileListings from '@/components/profile/ProfileListings.vue';
import ProfileListingsTable from '@/components/profile/ProfileListingsTable.vue';
import ProfileReviews from '@/components/profile/ProfileReviews.vue';
import type {
    Paginated,
    PetCard,
    ProfileSummary,
    ReportCategory,
    ReportReason,
    Review,
    ReviewBounds,
    SelectOption,
} from '@/types';

/**
 * A member's public page. Reachable by guests — `PetPolicy`-style public
 * visibility is a recorded decision in `UserPolicy::view`, not an oversight —
 * so nothing here may assume `auth.user`.
 *
 * The two paginators carry their own page names (`listings`, `reviews`) and are
 * turned independently; see `Pagination`'s `only` prop.
 *
 * The report option lists are props rather than an endpoint, and are handed
 * down to the review cards that host the report dialog.
 *
 * `has_reviewed` is what closes the review form: a second review by the same
 * author is refused by a unique index and by
 * `SubmitReview\EnsureNotAlreadyReviewed`, and until the flag existed the page
 * offered the form to everybody and explained afterwards.
 *
 * ## The owner gets a table, everybody else gets the grid
 *
 * `profile.is_self` decides (user's instruction, 2026-09-06): the owner sees
 * `ProfileListingsTable` — thumbnail, name, type, price, status, date, counts,
 * and edit / mark unavailable / remove controls on every row — with the
 * publish button in the section heading, and a visitor sees
 * `ProfileListings`, the card grid, nine to a page. `is_self` is a server
 * prop, so a prefetched or cached page could in principle show the wrong
 * shape; it cannot make a control *work* for the wrong person, because every
 * action on a row is authorized against `PetPolicy` on the server. Same
 * reasoning `pets/card/PetCardActions` records for hiding the message button
 * off `is_owner`.
 */
const { profile } = defineProps<{
    profile: ProfileSummary;
    listings: Paginated<PetCard>;
    reviews: Paginated<Review>;
    reportCategories: SelectOption<ReportCategory>[];
    reportReasons: SelectOption<ReportReason>[];
    /**
     * `petconnect.reviews.min_rate` / `max_rate` / `max_comment_length`, built
     * from the same accessors the validator's rules are. The star widget used
     * to hardcode five; nothing on this page may again.
     */
    reviewBounds: ReviewBounds;
}>();

const page = usePage();

const isGuest = computed(() => !page.props.auth.user);
const canInteract = computed(() => !isGuest.value && !profile.is_self);
const canReview = computed(() => canInteract.value && !profile.has_reviewed);
</script>

<template>
    <div class="mx-auto w-full max-w-5xl space-y-8 p-4 sm:p-6 lg:p-8">
        <Head :title="profile.name" />

        <ProfileHeader
            :profile="profile"
            :can-interact="canInteract"
            :bounds="reviewBounds"
        />

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">
                    Listings
                    <span class="text-muted-foreground font-normal">
                        ({{ listings.meta.total }})
                    </span>
                </h2>

                <CreatePetButton v-if="profile.is_self" />
            </div>

            <ProfileListingsTable v-if="profile.is_self" :listings="listings" />
            <ProfileListings v-else :listings="listings" :name="profile.name" />
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold">
                Reviews
                <span class="text-muted-foreground font-normal">
                    ({{ reviews.meta.total }})
                </span>
            </h2>
            <ProfileReviews
                :reviews="reviews"
                :subject-id="profile.id"
                :can-review="canReview"
                :bounds="reviewBounds"
                :report-categories="reportCategories"
                :report-reasons="reportReasons"
            />
        </section>
    </div>
</template>
