<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, EyeOff, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import PetRemoveListingDialog from '@/components/pets/PetRemoveListingDialog.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editPet } from '@/routes/pets';
import { toggle as toggleStatus } from '@/routes/pets/status';
import type { PetStatus } from '@/types';

/**
 * What the owner of a listing can do to it from its own page.
 *
 * The confirmation behind "Remove" is `PetRemoveListingDialog`, shared with
 * the owner's listings table on the profile page (`profile/ProfileListingRow`
 * renders the same three controls as icons); what retiring means — a soft
 * delete kept for moderation, never a purge — is recorded there.
 */
const { petId, status } = defineProps<{
    petId: number;
    status: PetStatus;
}>();

const { t } = useTranslations();

const confirming = ref(false);
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Button as-child variant="outline">
            <Link :href="editPet(petId)">
                <Pencil class="size-4" aria-hidden="true" />
                {{ t('common.edit') }}
            </Link>
        </Button>

        <Button as-child variant="outline">
            <Link :href="toggleStatus(petId)" as="button" preserve-scroll>
                <EyeOff
                    v-if="status === 'available'"
                    class="size-4"
                    aria-hidden="true"
                />
                <Eye v-else class="size-4" aria-hidden="true" />
                {{
                    status === 'available'
                        ? t('pets.mark_as_unavailable')
                        : t('pets.mark_as_available')
                }}
            </Link>
        </Button>

        <Button
            variant="outline"
            class="text-destructive"
            @click="confirming = true"
        >
            <Trash2 class="size-4" aria-hidden="true" />
            {{ t('common.remove') }}
        </Button>

        <PetRemoveListingDialog v-model:open="confirming" :pet-id="petId" />
    </div>
</template>
