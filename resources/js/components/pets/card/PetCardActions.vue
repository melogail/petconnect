<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { show as showPet } from '@/routes/pets';

/**
 * The card's action row.
 *
 * Today it holds one control: the explicit "View details" affordance that
 * replaced the anchor which used to wrap the whole card. It exists as its own
 * component so the like, comment, message and share controls that follow have
 * a valid place to land — none of them could have been nested inside the old
 * card-wide `<Link>` without putting a button inside an anchor.
 *
 * `aria-label` extends the visible text rather than replacing it ("View
 * details for Luna" contains "View details"), so the accessible name is
 * unambiguous in a list of links and still matches what a speech-input user
 * would say.
 *
 * `likes_count`, `comments_count` and `is_liked` — declared on the `PetCard`
 * type in `resources/js/types/pets.ts` (lines 97-100 as written) — are
 * deliberately unrendered while this row holds only "View details": the
 * passive counters were removed here on purpose, and those three fields come
 * back as the labels and pressed state of the like and comment controls in the
 * interactive-controls task. They are unused by intent, not by oversight, so
 * do not prune them from the resource as dead payload.
 */
defineProps<{
    petId: number;
    name: string;
}>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 pt-1">
        <Button as-child variant="outline" size="sm">
            <Link
                :href="showPet(petId)"
                :aria-label="`View details for ${name}`"
            >
                View details
                <ArrowRight class="size-4 rtl:rotate-180" aria-hidden="true" />
            </Link>
        </Button>
    </div>
</template>
