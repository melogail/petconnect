<script setup lang="ts">
import { Shield } from 'lucide-vue-next';

defineProps<{
    petDetails: {
        name: string;
        description: string;
        traits: string[];
    };
}>();
</script>

<template>
    <div>
        <!-- Pet Name Welcome -->
        <h2 class="mb-4 text-xl font-bold text-foreground">
            Meet {{ petDetails.name.split(' ')[0] }}!
        </h2>

        <!-- Description -->
        <p class="mb-6 leading-relaxed text-muted-foreground">
            {{ petDetails.description }}
        </p>

        <!-- Personality Traits -->
        <div v-if="petDetails.traits && petDetails.traits.length > 0">
            <h4 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                <Shield class="h-4 w-4 text-primary" />
                Personality Traits
            </h4>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="(trait, index) in petDetails.traits"
                    :key="index"
                    class="rounded-full border border-primary/20 bg-primary/5 px-3.5 py-1.5 text-sm font-medium text-primary transition-colors hover:bg-primary/10"
                >
                    {{ trait }}
                </span>
            </div>
        </div>
    </div>
</template>
