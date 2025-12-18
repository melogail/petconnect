<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';

interface Props {
    form: any;
    petTraits: Array<{ id: string; label: string }>;
}

const props = defineProps<Props>();
</script>

<template>
    <div id="step-5" class="step-container animate-fade-in">
        <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-purple-100/50 dark:border-purple-900/30 hover:border-purple-300 dark:hover:border-purple-700 shadow-lg">
            <!-- Animated Background Gradient -->
            <div class="absolute -z-10 inset-0 bg-gradient-to-br from-purple-50/30 via-violet-50/20 to-fuchsia-50/10 dark:from-purple-900/20 dark:via-violet-900/10 dark:to-fuchsia-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
            <!-- Decorative Corner -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-purple-100/20 to-transparent dark:from-purple-900/10 rounded-bl-full opacity-50"></div>
            <CardHeader class="relative z-10">
                <div class="flex items-center space-x-4">
                    <div class="relative p-3 rounded-2xl bg-gradient-to-br from-purple-500 to-fuchsia-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                        <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Description & Personality</CardTitle>
                        <CardDescription class="text-gray-500 dark:text-gray-400">Tell us about your pet's personality and traits</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        name="description"
                        v-model="form.description"
                        placeholder="Tell us about your pet's personality, habits, and any special needs..."
                        class="min-h-[120px]"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="space-y-3">
                    <Label>Personality Traits</Label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        <div v-for="trait in petTraits" :key="trait.id" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-purple-50 dark:hover:bg-purple-900/10 transition-colors">
                            <Checkbox
                                :id="`trait-${trait.id}`"
                                :checked="form.traits.includes(trait.id)"
                                @update:checked="(checked) => {
                                    if (checked) {
                                        form.traits.push(trait.id);
                                    } else {
                                        const index = form.traits.indexOf(trait.id);
                                        if (index > -1) form.traits.splice(index, 1);
                                    }
                                }"
                            />
                            <Label :for="`trait-${trait.id}`" class="cursor-pointer">{{ trait.label }}</Label>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
/* Fix text blurriness on hover/scale transforms */
.group:hover {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    transform-style: preserve-3d;
    -webkit-transform-style: preserve-3d;
}

/* Prevent text blur on transform elements */
[class*="transition"],
[class*="transform"],
[class*="scale"] {
    will-change: transform;
    transform: translateZ(0);
    -webkit-transform: translateZ(0);
}
</style>
