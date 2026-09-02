<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageSquare } from '@lucide/vue';
import { computed } from 'vue';
import { countLabel } from '@/components/pets/card/labels';
import { Button } from '@/components/ui/button';
import { show as showPet } from '@/routes/pets';

/**
 * `comments_count`, as a link to the listing page.
 *
 * A link and **not** a dialog. `CommentThread` needs four props the feed
 * payload does not carry — the thread endpoint's page size, the composer's max
 * length and the two report vocabularies — so the thread can only be mounted on
 * the page that ships them. Opening one here would mean adding those four to
 * every row of the feed.
 *
 * It is also the one control on the row that is identical for a guest: reading
 * comments goes through `pets.show`, which is public, so there is no
 * signed-in branch and nothing to route to `login`.
 *
 * The visible label is the bare number, because that is what fits; the
 * accessible name says what the number counts and contains the visible text,
 * so speech input still matches it.
 */
const { commentsCount, name } = defineProps<{
    petId: number;
    name: string;
    commentsCount: number;
}>();

const label = computed(
    () => `${countLabel(commentsCount, 'comment')} on ${name}`,
);
</script>

<template>
    <Button as-child variant="outline">
        <Link :href="showPet(petId)" :aria-label="label">
            <MessageSquare class="size-4" aria-hidden="true" />
            {{ commentsCount }}
        </Link>
    </Button>
</template>
