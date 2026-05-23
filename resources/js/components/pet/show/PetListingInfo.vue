<script setup lang="ts">
import { Tag, DollarSign, Eye } from 'lucide-vue-next';

const listingTypeLabels: Record<number, { label: string; class: string }> = {
    1: {
        label: 'Adoption',
        class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    },
    2: {
        label: 'Sale',
        class: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    },
    3: {
        label: 'Mating',
        class: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    },
};

const props = defineProps<{
    petDetails: {
        listing_type: number;
        listing_type_label: string;
        price?: string | number | null;
        status: string;
        views: number;
    };
}>();
</script>

<template>
    <div
        class="border-border/50 from-muted/30 to-muted/10 mb-6 flex flex-wrap items-center gap-3 rounded-2xl border bg-gradient-to-r px-5 py-4"
    >
        <!-- Listing Type Badge -->
        <span
            class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-semibold"
            :class="
                listingTypeLabels[petDetails.listing_type]?.class ||
                'bg-muted text-muted-foreground'
            "
        >
            <Tag class="h-3.5 w-3.5" />
            {{ petDetails.listing_type_label }}
        </span>

        <!-- Price -->
        <span
            v-if="petDetails.listing_type === 2 && petDetails.price"
            class="text-foreground inline-flex items-center gap-1 text-2xl font-bold"
        >
            <DollarSign class="h-5 w-5 text-green-500" />
            {{ Number(petDetails.price).toLocaleString() }}
        </span>

        <div
            class="text-muted-foreground ml-auto flex items-center gap-4 text-sm"
        >
            <span class="capitalize"
                >Status:
                <strong class="text-foreground">{{
                    petDetails.status
                }}</strong></span
            >
            <span class="flex items-center gap-1">
                <Eye class="h-4 w-4" />
                {{ petDetails.views.toLocaleString() }}
            </span>
        </div>
    </div>
</template>
