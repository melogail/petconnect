<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import CommentThread from '@/components/comments/CommentThread.vue';
import PetDetailHeader from '@/components/pets/PetDetailHeader.vue';
import PetFactsCard from '@/components/pets/PetFactsCard.vue';
import PetGallery from '@/components/pets/PetGallery.vue';
import PetOwnerCard from '@/components/pets/PetOwnerCard.vue';
import PetSafetyTips from '@/components/pets/PetSafetyTips.vue';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';
import type {
    CommentBounds,
    PetDetail,
    ReportCategory,
    ReportReason,
    SelectOption,
} from '@/types';

/**
 * One listing. Public — a shared link has to work for somebody with no account.
 *
 * ## The layout is legacy's, and it is one card, not three
 *
 * A back link, then a two-column flex: a `flex-1` main column carrying the
 * gallery, the title card, the facts card and the comments, and a
 * `lg:w-80 xl:w-96` sidebar that sticks at `top-20` and holds the owner panel
 * and the safety tips. Below `lg` the sidebar simply falls under the main
 * column, which is why it is a wrapping flex row rather than a grid.
 *
 * This page previously stacked a full-bleed gallery and a bare header above a
 * three-column grid of separate `Card`s — about, attributes and health on the
 * left, owner and location on the right — with the comments loose underneath.
 * Every fact now lives in the **single** card `PetFactsCard` assembles, which
 * is what legacy does; the sidebar is left with the two things that are about
 * the transaction rather than about the animal.
 *
 * ## The payload
 *
 * - The **owner-only leaves** (`location.address`, `location.detailedAddress`,
 *   `location.coordinates`, `health.medications`, `health.allergies`,
 *   `health.vetName`, `health.vetPhone`) are *absent*, not null, for anybody
 *   who cannot update the listing. Every panel coalesces every leaf rather than
 *   gating on `is_owner` a second time.
 * - The **comment thread is bounded**: at most 20 roots with at most 3 replies
 *   each, while `comments_count` is the true total and `root_comments_count`
 *   the total of top-level comments alone. `CommentThread` pages the rest from
 *   `comments.index` / `comments.replies`, and needs the root count because the
 *   endpoint pages roots.
 * - `commentBounds` carries both of the thread's bounds. `max_length` is the
 *   composer's ceiling, built from the same accessor the `max:` rule is, and
 *   `thread_per_page` is the endpoint's page size, which the client cannot
 *   infer from the size of the slice it was shipped. Nothing here hardcodes
 *   either.
 *
 * The report vocabulary ships as props because this page hosts the *comment*
 * report dialog; a pet is not reportable at all (`Enums\Reportable` is
 * `comment|review`), so there is no report control on the listing itself.
 *
 * ## Guests
 *
 * `auth.user` is typed non-nullable and is **null here** — this is one of the
 * five pages `app.ts` maps to `PublicLayout` (.ai/rules/types.md). Every write
 * on the page is behind `auth` + `verified`, so each control either routes to
 * `login` or is absent, and `isSignedIn` is the single place that is decided.
 */
const { pet } = defineProps<{
    pet: PetDetail;
    reportCategories: SelectOption<ReportCategory>[];
    reportReasons: SelectOption<ReportReason>[];
    commentBounds: CommentBounds;
}>();

const { t } = useTranslations();

const page = usePage();

/** Every write on this page needs an account; the backend needs it verified. */
const isSignedIn = computed(() => Boolean(page.props.auth.user));

const canMessageOwner = computed(() => isSignedIn.value && !pet.is_owner);
</script>

<template>
    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6">
        <Head :title="pet.name" />

        <div class="mb-6">
            <Link
                :href="home()"
                class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm transition-colors"
            >
                <ArrowLeft class="size-4 rtl:rotate-180" aria-hidden="true" />
                {{ t('pets.back_to_pets') }}
            </Link>
        </div>

        <div class="flex flex-col gap-8 lg:flex-row">
            <div class="min-w-0 flex-1">
                <div class="mb-8">
                    <PetGallery
                        :photos="pet.photos"
                        :featured-image="pet.featured_image"
                        :name="pet.name"
                    />
                </div>

                <PetDetailHeader
                    :pet="pet"
                    :can-like="isSignedIn"
                    :can-message="canMessageOwner"
                />

                <PetFactsCard :pet="pet" />

                <CommentThread
                    :comments="pet.comments"
                    :comments-count="pet.comments_count"
                    :root-comments-count="pet.root_comments_count"
                    commentable-type="pet"
                    :commentable-id="pet.id"
                    :max-length="commentBounds.max_length"
                    :thread-per-page="commentBounds.thread_per_page"
                    :can-interact="isSignedIn"
                    :report-categories="reportCategories"
                    :report-reasons="reportReasons"
                />
            </div>

            <div class="w-full lg:w-80 xl:w-96">
                <div class="sticky top-20 space-y-5">
                    <PetOwnerCard
                        v-if="pet.user"
                        :owner="pet.user"
                        :can-message="canMessageOwner"
                    />
                    <PetSafetyTips />
                </div>
            </div>
        </div>
    </div>
</template>
