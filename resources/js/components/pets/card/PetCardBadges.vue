<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { PetListingType, PetStatus } from '@/types';

/**
 * The two badges that classify a listing at a glance.
 *
 * `status` leads because it is the one that can rule a listing out; the
 * listing type is `outline` so the pair reads as heading plus qualifier
 * rather than two competing emphases.
 */
defineProps<{
    status: PetStatus;
    listingType: PetListingType;
}>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <Badge
            :variant="status === 'available' ? 'default' : 'secondary'"
            class="capitalize"
        >
            {{ status }}
        </Badge>
        <Badge variant="outline" class="capitalize">{{ listingType }}</Badge>
    </div>
</template>
