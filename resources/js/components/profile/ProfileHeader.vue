<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BadgeCheck, CalendarDays, MapPin, Settings } from '@lucide/vue';
import { computed } from 'vue';
import StartConversationButton from '@/components/messaging/StartConversationButton.vue';
import ProfileLikeButton from '@/components/profile/ProfileLikeButton.vue';
import ProfileStat from '@/components/profile/ProfileStat.vue';
import RatingStars from '@/components/reviews/RatingStars.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/composables/useLocale';
import { formatDate } from '@/lib/datetime';
import { edit as editProfile } from '@/routes/profile';
import type { ProfileSummary, ReviewBounds } from '@/types';

/**
 * The identity block at the top of a public profile.
 *
 * Everything here is public by construction: `location` is the coarse
 * "City, State, Country" accessor, and the payload carries no email, phone,
 * address or coordinate at all.
 */
const { profile, canInteract } = defineProps<{
    profile: ProfileSummary;
    /**
     * A signed-in viewer who is not the subject. Both writes on this header —
     * opening a thread and liking — need one.
     */
    canInteract: boolean;
    /** `petconnect.reviews.*`, so the stars draw the real scale. */
    bounds: ReviewBounds;
}>();

const { tag } = useLocale();

const joinedOn = computed(() => formatDate(profile.created_at, tag.value));
const rating = computed(() =>
    profile.reviews_avg_rate === null
        ? '—'
        : profile.reviews_avg_rate.toFixed(1),
);
</script>

<template>
    <header
        class="border-border bg-card flex flex-col gap-6 rounded-xl border p-6 sm:flex-row sm:items-start"
    >
        <UserAvatar
            :name="profile.name"
            :avatar="profile.avatar"
            class="size-20 shrink-0 sm:size-24"
        />

        <div class="min-w-0 flex-1 space-y-3">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold">{{ profile.name }}</h1>
                    <BadgeCheck
                        v-if="profile.is_verified"
                        class="size-5 text-sky-500"
                        aria-label="Verified member"
                    />
                </div>
                <p
                    v-if="profile.username"
                    class="text-muted-foreground text-sm"
                >
                    @{{ profile.username }}
                </p>
            </div>

            <div
                class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
            >
                <span v-if="profile.location" class="flex items-center gap-1.5">
                    <MapPin class="size-4" />
                    {{ profile.location }}
                </span>
                <span class="flex items-center gap-1.5">
                    <CalendarDays class="size-4" />
                    Joined {{ joinedOn }}
                </span>
            </div>

            <p
                v-if="profile.bio"
                class="max-w-prose text-sm leading-relaxed whitespace-pre-line"
            >
                {{ profile.bio }}
            </p>

            <dl class="flex flex-wrap gap-6">
                <ProfileStat label="Listings" :value="profile.pets_count" />
                <ProfileStat label="Reviews" :value="profile.reviews_count" />
                <ProfileStat label="Rating" :value="rating">
                    <RatingStars
                        :rate="profile.reviews_avg_rate"
                        :max="bounds.max_rate"
                        class="mt-1"
                    />
                </ProfileStat>
            </dl>
        </div>

        <div class="flex shrink-0 flex-wrap gap-2">
            <Button v-if="profile.can_update" as-child variant="outline">
                <Link :href="editProfile()">
                    <Settings class="size-4" />
                    Edit profile
                </Link>
            </Button>

            <ProfileLikeButton
                v-if="!profile.is_self"
                :user-id="profile.id"
                :is-liked="profile.is_liked"
                :can-like="canInteract"
            />

            <StartConversationButton
                v-if="canInteract"
                :recipient-id="profile.id"
                :recipient-name="profile.name"
            />
        </div>
    </header>
</template>
