<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, EyeOff, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';
import { destroy as destroyPet, edit as editPet } from '@/routes/pets';
import { toggle as toggleStatus } from '@/routes/pets/status';
import type { PetStatus } from '@/types';

/**
 * What the owner of a listing can do to it from its own page.
 *
 * `pets.destroy` **retires** the listing — `Actions\Pets\DeletePet` soft
 * deletes it and keeps the row, its photos and its thread for moderation — so
 * the wording is "remove", not "delete forever". Purging one is a Nova action
 * and has no route on this guard at all.
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

        <Dialog v-model:open="confirming">
            <DialogTrigger as-child>
                <Button variant="outline" class="text-destructive">
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{ t('common.remove') }}
                </Button>
            </DialogTrigger>

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
                    <Button variant="outline" @click="confirming = false">
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
    </div>
</template>
