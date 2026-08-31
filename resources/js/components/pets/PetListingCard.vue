<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Heart, MapPin, MessageSquare, PawPrint } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useLocale } from '@/composables/useLocale';
import { show as showPet } from '@/routes/pets';
import type { PetCard } from '@/types';

/** One listing, as a grid tile. Read-only: it links, it does not act. */
const { pet } = defineProps<{ pet: PetCard }>();

const { tag } = useLocale();

const place = computed(() =>
    [pet.city, pet.state, pet.country].filter(Boolean).join(', '),
);

/** Only present when the feed query ran with a distance calculation. */
const distance = computed(() =>
    pet.distance === undefined ? null : `${pet.distance} km`,
);

const price = computed(() =>
    pet.price === null
        ? null
        : new Intl.NumberFormat(tag.value, {
              maximumFractionDigits: 2,
          }).format(pet.price),
);
</script>

<template>
    <Card class="overflow-hidden py-0 transition-shadow hover:shadow-md">
        <Link :href="showPet(pet.id)" class="block">
            <div
                class="bg-muted relative flex aspect-4/3 items-center justify-center"
            >
                <img
                    v-if="pet.image"
                    :src="pet.image"
                    :alt="pet.name"
                    class="size-full object-cover"
                    loading="lazy"
                />
                <PawPrint v-else class="text-muted-foreground size-10" />

                <Badge
                    v-if="distance"
                    variant="secondary"
                    class="absolute top-2 left-2"
                >
                    <MapPin class="size-3" />
                    {{ distance }}
                </Badge>
            </div>

            <CardContent class="space-y-2 p-4">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="truncate font-medium">{{ pet.name }}</h3>
                    <Badge
                        :variant="
                            pet.status === 'available' ? 'default' : 'secondary'
                        "
                        class="capitalize"
                    >
                        {{ pet.status }}
                    </Badge>
                </div>

                <p class="text-muted-foreground truncate text-sm">
                    {{ pet.breed?.name ?? pet.category?.name ?? 'Pet' }}
                    <template v-if="place"> · {{ place }}</template>
                </p>

                <div
                    class="text-muted-foreground flex items-center gap-3 text-xs"
                >
                    <span class="capitalize">{{ pet.listing_type }}</span>
                    <span v-if="price" class="text-foreground font-medium">
                        {{ price }}
                    </span>
                    <span class="ms-auto flex items-center gap-1">
                        <Heart
                            class="size-3.5"
                            :class="pet.is_liked ? 'fill-current' : ''"
                        />{{ pet.likes_count }}
                    </span>
                    <span class="flex items-center gap-1">
                        <MessageSquare class="size-3.5" />{{
                            pet.comments_count
                        }}
                    </span>
                </div>
            </CardContent>
        </Link>
    </Card>
</template>
