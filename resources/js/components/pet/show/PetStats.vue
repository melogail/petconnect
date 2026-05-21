<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircle2, XCircle, Scale, Palette, Users, PawPrint, Info } from 'lucide-vue-next';

const props = defineProps<{
    petDetails: {
        vaccinated: boolean;
        spayedNeutered: boolean;
        weight?: string | number | null;
        color?: string | null;
        goodWithKids?: string | null;
        goodWithPets?: string | null;
        size?: string | null;
    };
}>();

const stats = computed(() => {
    const items: Array<{ icon: any; label: string; value: string; highlight?: boolean }> = [
        {
            icon: CheckCircle2,
            label: 'Vaccination',
            value: props.petDetails.vaccinated ? 'Up to date' : 'Needed',
            highlight: props.petDetails.vaccinated,
        },
        {
            icon: props.petDetails.spayedNeutered ? CheckCircle2 : XCircle,
            label: 'Spayed / Neutered',
            value: props.petDetails.spayedNeutered ? 'Yes' : 'No',
            highlight: props.petDetails.spayedNeutered,
        },
        ...(props.petDetails.weight ? [{
            icon: Scale,
            label: 'Weight',
            value: `${props.petDetails.weight} kg`,
            highlight: true,
        }] : []),
        ...(props.petDetails.color ? [{
            icon: Palette,
            label: 'Color',
            value: props.petDetails.color,
            highlight: true,
        }] : []),
        ...(props.petDetails.goodWithKids ? [{
            icon: Users,
            label: 'With Kids',
            value: props.petDetails.goodWithKids,
            highlight: true,
        }] : []),
        ...(props.petDetails.goodWithPets ? [{
            icon: PawPrint,
            label: 'With Other Pets',
            value: props.petDetails.goodWithPets,
            highlight: true,
        }] : []),
        ...(props.petDetails.size ? [{
            icon: Info,
            label: 'Size',
            value: props.petDetails.size,
            highlight: true,
        }] : []),
    ];
    return items;
});
</script>

<template>
    <div class="mb-6">
        <h3 class="mb-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
            Quick Info
        </h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="(stat, i) in stats"
                :key="i"
                class="group relative overflow-hidden rounded-xl border border-border/50 bg-card p-4 transition-all duration-200 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5"
            >
                <!-- Background glow on hover -->
                <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100 rounded-xl" />
                <component
                    :is="stat.icon"
                    class="mb-2 h-5 w-5 transition-colors"
                    :class="stat.highlight ? 'text-primary' : 'text-muted-foreground'"
                />
                <p class="mb-0.5 text-xs text-muted-foreground">{{ stat.label }}</p>
                <p class="font-semibold capitalize text-foreground text-sm">{{ stat.value }}</p>
            </div>
        </div>
    </div>
</template>
