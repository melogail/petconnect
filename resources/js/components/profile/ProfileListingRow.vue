<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, EyeOff, PawPrint, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PetRemoveListingDialog from '@/components/pets/PetRemoveListingDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/datetime';
import { taxonomyName } from '@/lib/taxonomy';
import { edit as editPet, show as showPet } from '@/routes/pets';
import { toggle as toggleStatus } from '@/routes/pets/status';
import type { PetCard } from '@/types';

/**
 * One of the owner's listings, as a row of `ProfileListingsTable`.
 *
 * The port of legacy's `components/web/profile/ProfilePetsTable.vue` row: a
 * thumbnail, the name, a green/yellow availability pill, the date, the view
 * count and three icon controls at the end. Kept from this app's card and
 * absent from legacy: the listing type, the price, and the like and comment
 * counts — the resource already pays for them, and a table is where a number
 * per row earns its column.
 *
 * ## The three controls are the ones `PetOwnerActions` renders, in icon form
 *
 * Same routes, same wording keys, same confirmation (`PetRemoveListingDialog`
 * is shared). They are icon-only here because the outline buttons with their
 * labels are three controls wide and this cell is one of nine. An icon-only
 * control has no visible text, so its `aria-label` is the whole accessible
 * name (the case `messaging/StartConversationButton` records for
 * `appearance="icon"`), and each name carries the pet's name so that the nine
 * "Edit" controls on a page do not announce identically — colliding
 * accessible names are a page-level property no per-row review sees
 * (.ai/rules/general.md, "The page is a review subject in its own right").
 * The tooltip shows the same string, so pointer and screen reader read one
 * sentence.
 *
 * Availability is a `<Link as="button">` to `pets.status.toggle` with
 * `preserve-scroll`, as on the detail page: the server answers `back()`, which
 * re-renders this page on the same listings page, and the row re-reads
 * `pet.status` off the fresh prop. Nothing is toggled locally.
 *
 * `views`, `likes_count` and `comments_count` go through `Intl.NumberFormat`
 * so a reader gets the digits their locale wants. `price` takes the coercion
 * `PetListingCard` records: `pets.price` is an uncast decimal, a float on
 * SQLite and a string on MySQL, so `Number()` first.
 */
const { pet } = defineProps<{ pet: PetCard }>();

const { t } = useTranslations();
const { locale, tag } = useLocale();

const confirming = ref(false);

const isAvailable = computed(() => pet.status === 'available');

/** Breed names the listing best; category is the fallback. */
const kind = computed(() => {
    const taxon = pet.breed ?? pet.category;

    return taxon ? taxonomyName(taxon, locale.value.current) : null;
});

const price = computed(() =>
    pet.price === null
        ? null
        : new Intl.NumberFormat(tag.value, {
              maximumFractionDigits: 2,
          }).format(Number(pet.price)),
);

const listedOn = computed(() => formatDate(pet.created_at, tag.value));

function count(value: number): string {
    return new Intl.NumberFormat(tag.value).format(value);
}

const editLabel = computed(() =>
    t('pets.edit_listing_named', { name: pet.name }),
);

const toggleLabel = computed(() =>
    isAvailable.value
        ? t('pets.mark_unavailable_named', { name: pet.name })
        : t('pets.mark_available_named', { name: pet.name }),
);

const removeLabel = computed(() =>
    t('pets.remove_listing_named', { name: pet.name }),
);
</script>

<template>
    <tr class="hover:bg-muted/50 transition-colors">
        <td class="px-4 py-3">
            <div class="flex items-center gap-3">
                <div
                    class="bg-muted size-10 shrink-0 overflow-hidden rounded-lg"
                >
                    <img
                        v-if="pet.image"
                        :src="pet.image"
                        alt=""
                        class="size-full object-cover"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="text-muted-foreground flex size-full items-center justify-center"
                    >
                        <PawPrint class="size-5" aria-hidden="true" />
                    </div>
                </div>

                <div class="min-w-0">
                    <Link
                        :href="showPet(pet.id)"
                        class="text-foreground hover:text-primary focus-visible:ring-ring/50 block truncate rounded-sm text-sm font-semibold hover:underline focus-visible:ring-[3px] focus-visible:outline-none"
                    >
                        {{ pet.name }}
                    </Link>
                    <p
                        v-if="kind"
                        class="text-muted-foreground truncate text-xs"
                    >
                        {{ kind }}
                    </p>
                </div>
            </div>
        </td>

        <td class="px-4 py-3 text-sm whitespace-nowrap">
            {{ t(`listing_types.${pet.listing_type}`) }}
        </td>

        <td class="px-4 py-3 text-sm whitespace-nowrap tabular-nums">
            {{ price ?? '—' }}
        </td>

        <td class="px-4 py-3 whitespace-nowrap">
            <span
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                :class="
                    isAvailable
                        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                "
            >
                <span
                    class="size-1.5 rounded-full"
                    :class="
                        isAvailable
                            ? 'bg-green-500 dark:bg-green-400'
                            : 'bg-yellow-500 dark:bg-yellow-400'
                    "
                    aria-hidden="true"
                ></span>
                {{ isAvailable ? t('pets.available') : t('pets.unavailable') }}
            </span>
        </td>

        <td class="text-muted-foreground px-4 py-3 text-sm whitespace-nowrap">
            {{ listedOn }}
        </td>

        <td
            class="text-muted-foreground px-4 py-3 text-end text-sm tabular-nums"
        >
            {{ count(pet.views) }}
        </td>

        <td
            class="text-muted-foreground px-4 py-3 text-end text-sm tabular-nums"
        >
            {{ count(pet.likes_count) }}
        </td>

        <td
            class="text-muted-foreground px-4 py-3 text-end text-sm tabular-nums"
        >
            {{ count(pet.comments_count) }}
        </td>

        <td class="px-4 py-3">
            <div class="flex items-center justify-end gap-1">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            as-child
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="editLabel"
                        >
                            <Link :href="editPet(pet.id)">
                                <Pencil class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ editLabel }}</TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            as-child
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="toggleLabel"
                        >
                            <Link
                                :href="toggleStatus(pet.id)"
                                as="button"
                                preserve-scroll
                            >
                                <EyeOff
                                    v-if="isAvailable"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <Eye v-else class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ toggleLabel }}</TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-destructive hover:text-destructive"
                            :aria-label="removeLabel"
                            @click="confirming = true"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ removeLabel }}</TooltipContent>
                </Tooltip>

                <PetRemoveListingDialog
                    v-model:open="confirming"
                    :pet-id="pet.id"
                />
            </div>
        </td>
    </tr>
</template>
