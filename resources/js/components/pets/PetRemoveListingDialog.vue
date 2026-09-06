<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';
import { destroy as destroyPet } from '@/routes/pets';

/**
 * Confirm before retiring a listing.
 *
 * `pets.destroy` **retires** the listing — `Actions\Pets\DeletePet` soft
 * deletes it and keeps the row, its photos and its thread for moderation — so
 * the wording is "remove", not "delete forever". Purging one is a Nova action
 * and has no route on this guard at all.
 *
 * Driven from outside (`v-model:open`) rather than carrying its own trigger,
 * because it has two: the outline "Remove" button on the listing's own page
 * (`PetOwnerActions`) and the icon control on a row of the owner's listings
 * table (`profile/ProfileListingRow`). The copy and the destination are the
 * same from both; only the trigger differs, so the trigger is the caller's.
 * Same model as `comments/CommentDeleteDialog`.
 *
 * The server answers with a redirect to the owner's profile (see
 * `PetController::destroy`), which is where both callers want to land: the
 * listing's own page is gone once it is retired, and the table is on the
 * profile already.
 */
const { petId } = defineProps<{ petId: number }>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useTranslations();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ t('pets.remove_listing_question') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('pets.remove_listing_desc') }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button variant="outline" @click="open = false">
                    {{ t('pets.cancel') }}
                </Button>
                <Button as-child variant="destructive">
                    <Link :href="destroyPet(petId)" as="button">
                        {{ t('pets.remove_listing') }}
                    </Link>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
