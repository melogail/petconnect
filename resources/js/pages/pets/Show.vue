<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CommentThread from '@/components/comments/CommentThread.vue';
import PetAboutCard from '@/components/pets/PetAboutCard.vue';
import PetAttributesCard from '@/components/pets/PetAttributesCard.vue';
import PetDetailHeader from '@/components/pets/PetDetailHeader.vue';
import PetGallery from '@/components/pets/PetGallery.vue';
import PetHealthCard from '@/components/pets/PetHealthCard.vue';
import PetLocationCard from '@/components/pets/PetLocationCard.vue';
import PetOwnerCard from '@/components/pets/PetOwnerCard.vue';
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
 * Three things about the payload drive this page:
 *
 * - The **owner-only leaves** (`location.address`, `location.detailedAddress`,
 *   `location.coordinates`, `health.medications`, `health.allergies`,
 *   `health.vetName`, `health.vetPhone`) are *absent*, not null, for anybody
 *   who cannot update the listing. Each panel gates on `is_owner` and still
 *   coalesces every leaf.
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
 */
const { pet } = defineProps<{
    pet: PetDetail;
    reportCategories: SelectOption<ReportCategory>[];
    reportReasons: SelectOption<ReportReason>[];
    commentBounds: CommentBounds;
}>();

const page = usePage();

/** Every write on this page needs an account; the backend needs it verified. */
const isSignedIn = computed(() => Boolean(page.props.auth.user));

const canMessageOwner = computed(() => isSignedIn.value && !pet.is_owner);
</script>

<template>
    <div class="mx-auto w-full max-w-6xl space-y-8 px-4 py-8 sm:px-6">
        <Head :title="pet.name" />

        <PetGallery
            :photos="pet.photos"
            :featured-image="pet.featured_image"
            :name="pet.name"
        />

        <PetDetailHeader :pet="pet" :can-like="isSignedIn" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <PetAboutCard :pet="pet" />
                <PetAttributesCard :pet="pet" />
                <PetHealthCard :pet="pet" />
            </div>

            <div class="space-y-6">
                <PetOwnerCard
                    v-if="pet.user"
                    :owner="pet.user"
                    :can-message="canMessageOwner"
                />
                <PetLocationCard :pet="pet" />
            </div>
        </div>

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
</template>
