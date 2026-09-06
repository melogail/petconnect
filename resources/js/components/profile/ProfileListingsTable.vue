<script setup lang="ts">
import { PawPrint } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import ProfileListingRow from '@/components/profile/ProfileListingRow.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated, PetCard } from '@/types';

/**
 * The owner's own listings, as a table.
 *
 * `pages/profile/Show.vue` mounts this when `profile.is_self` and
 * `ProfileListings` (the card grid) otherwise — the user's instruction
 * (2026-09-06), and what legacy did with
 * `components/web/profile/ProfilePetsTable.vue`. A visitor wants to browse;
 * the owner wants to manage, and management is a scan down one column and a
 * control at the end of the row.
 *
 * Same paginator as the grid, under the same `listings` page name, so the
 * `Pagination` links stay scoped to that prop and turning a page does not
 * reset the reviews beside it. The page size is
 * `petconnect.profiles.listings_per_page` for both.
 *
 * `overflow-x-auto` on the wrapper, not on the page: nine columns do not fit a
 * phone, and wide content scrolls inside its own box while the body never
 * scrolls sideways. Header cells are `text-start` / `text-end` rather than
 * left / right so the numeric columns stay on the reading end under
 * `dir="rtl"`.
 *
 * One `TooltipProvider` around the whole table rather than one per row: the
 * provider is renderless and holds the shared open-delay state, so moving
 * between two rows' controls opens the second tooltip without the full delay.
 *
 * The empty state's copy is legacy's (`pets.no_pets_yet`,
 * `pets.get_started_create_post`); the publish button itself sits in the
 * section heading in `Show.vue`, where legacy's table header had it.
 */
defineProps<{
    listings: Paginated<PetCard>;
}>();

const { t } = useTranslations();

const HEADING =
    'text-muted-foreground px-4 py-3 text-xs font-semibold tracking-wider uppercase';
</script>

<template>
    <section class="space-y-4">
        <EmptyState
            v-if="listings.data.length === 0"
            :icon="PawPrint"
            :title="t('pets.no_pets_yet')"
            :description="t('pets.get_started_create_post')"
        />

        <template v-else>
            <TooltipProvider>
                <div
                    class="border-border bg-card overflow-hidden rounded-xl border shadow-sm"
                >
                    <div class="overflow-x-auto">
                        <table class="divide-border min-w-full divide-y">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th
                                        scope="col"
                                        :class="[HEADING, 'text-start']"
                                    >
                                        {{ t('pets.pet_name') }}
                                    </th>
                                    <th
                                        scope="col"
                                        :class="[HEADING, 'text-start']"
                                    >
                                        {{ t('pets.listing_type') }}
                                    </th>
                                    <th
                                        scope="col"
                                        :class="[HEADING, 'text-start']"
                                    >
                                        {{ t('pets.price') }}
                                    </th>
                                    <th
                                        scope="col"
                                        :class="[HEADING, 'text-start']"
                                    >
                                        {{ t('pets.status') }}
                                    </th>
                                    <th
                                        scope="col"
                                        :class="[HEADING, 'text-start']"
                                    >
                                        {{ t('pets.created_at') }}
                                    </th>
                                    <th
                                        scope="col"
                                        :class="[HEADING, 'text-end']"
                                    >
                                        {{ t('pets.views') }}
                                    </th>
                                    <th
                                        scope="col"
                                        :class="[HEADING, 'text-end']"
                                    >
                                        {{ t('pets.likes') }}
                                    </th>
                                    <th
                                        scope="col"
                                        :class="[HEADING, 'text-end']"
                                    >
                                        {{ t('pets.comments') }}
                                    </th>
                                    <th
                                        scope="col"
                                        :class="[HEADING, 'text-end']"
                                    >
                                        <span class="sr-only">
                                            {{ t('pets.actions') }}
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-border divide-y">
                                <ProfileListingRow
                                    v-for="pet in listings.data"
                                    :key="pet.id"
                                    :pet="pet"
                                />
                            </tbody>
                        </table>
                    </div>
                </div>
            </TooltipProvider>

            <Pagination :links="listings.meta.links" :only="['listings']" />
        </template>
    </section>
</template>
