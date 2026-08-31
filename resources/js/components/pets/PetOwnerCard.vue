<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { UserRound } from '@lucide/vue';
import StartConversationButton from '@/components/messaging/StartConversationButton.vue';
import PetPanel from '@/components/pets/PetPanel.vue';
import { Button } from '@/components/ui/button';
import UserAvatar from '@/components/UserAvatar.vue';
import { show as showProfile } from '@/routes/profile';
import type { PetOwner } from '@/types';

/**
 * Who published the listing.
 *
 * `owner` is absent when the loader did not eager load the relation, so the
 * whole panel is conditional on the page rather than on a null check inside it.
 */
const { owner, canMessage } = defineProps<{
    owner: PetOwner;
    /** A signed-in viewer who is not the owner may open a thread. */
    canMessage: boolean;
}>();
</script>

<template>
    <PetPanel title="Listed by" :icon="UserRound">
        <div class="space-y-4">
            <Link :href="showProfile(owner.id)" class="flex items-center gap-3">
                <UserAvatar
                    :name="owner.name"
                    :avatar="owner.avatar"
                    class="size-12"
                />
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ owner.name }}</p>
                    <p
                        v-if="owner.location"
                        class="text-muted-foreground truncate text-sm"
                    >
                        {{ owner.location }}
                    </p>
                </div>
            </Link>

            <div class="flex flex-wrap gap-2">
                <StartConversationButton
                    v-if="canMessage"
                    :recipient-id="owner.id"
                    :recipient-name="owner.name"
                />
                <Button as-child variant="outline">
                    <Link :href="showProfile(owner.id)"> View profile </Link>
                </Button>
            </div>
        </div>
    </PetPanel>
</template>
