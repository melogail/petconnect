<script setup lang="ts">
/**
 * The listing's own words.
 *
 * `description` is a non-nullable column, but it can be an empty string, and
 * nothing upstream filters for that: `PetListingCard` writes
 * `<PetCardDescription :description="pet.description" />` with no `v-if`, and
 * both of its consumers — `PetFeed.vue` and `profile/ProfileListings.vue` —
 * go through that one call site. The `v-if` below is therefore the only guard
 * there is; do not delete it on the assumption that a caller filters first. Clamped to two lines: the card is a teaser, and an
 * unclamped description makes tiles in the same grid row wildly different
 * heights.
 */
defineProps<{
    description: string;
}>();
</script>

<template>
    <p
        v-if="description"
        class="text-muted-foreground line-clamp-2 text-sm break-words"
    >
        {{ description }}
    </p>
</template>
