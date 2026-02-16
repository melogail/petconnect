<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/InputError.vue';

interface Props {
    form: any;
    categories: Array<{ id: string; name: string }>;
    breeds: Record<string, Array<{ id: string; name: string }>>;
    listingTypes: Array<{ value: number; label: string }>;
}

const props = defineProps<Props>();
const filteredBreeds = computed(() => {
    return props.form.type
        ? props.breeds[props.form.type as keyof typeof props.breeds]
        : [];
});
</script>

<template>
    <div id="step-1" class="step-container animate-fade-in">
        <Card
            class="group relative overflow-hidden border-2 border-primary-100/50 shadow-lg backdrop-blur-md transition-all duration-300 hover:border-primary-300 hover:shadow-2xl dark:border-primary-900/30 dark:bg-gray-800/70 dark:hover:border-primary-700"
        >
            <!-- Animated Background Gradient -->
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-br from-primary-50/30 via-purple-50/20 to-pink-50/10 opacity-0 transition-opacity duration-700 group-hover:opacity-100 dark:from-primary-900/20 dark:via-purple-900/10 dark:to-pink-900/5"
            ></div>
            <!-- Decorative Corner -->
            <div
                class="absolute right-0 top-0 h-32 w-32 rounded-bl-full bg-gradient-to-br from-primary-100/20 to-transparent opacity-50 dark:from-primary-900/10"
            ></div>
            <CardHeader class="relative z-10">
                <div class="flex items-start space-x-4 sm:items-center">
                    <div
                        class="relative rounded-2xl bg-gradient-to-br from-primary-500 to-purple-600 p-3 text-white shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:shadow-xl"
                    >
                        <div
                            class="absolute inset-0 animate-pulse rounded-2xl bg-white/20"
                        ></div>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="relative z-10 h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                            />
                        </svg>
                    </div>
                    <div>
                        <CardTitle
                            class="text-xl font-bold text-gray-800 dark:text-white"
                            >Basic Information</CardTitle
                        >
                        <CardDescription
                            class="text-gray-500 dark:text-gray-400"
                            >Tell us about your pet's basic
                            details</CardDescription
                        >
                    </div>
                </div>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="name" is-required>Pet Name</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="type" is-required>Pet Type</Label>
                    <Select v-model="form.type" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select pet type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.type" />
                </div>

                <div class="space-y-2">
                    <Label for="breed" is-required>Breed</Label>
                    <Select
                        v-model="form.breed"
                        :disabled="!form.type"
                        required
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select breed" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="breed in filteredBreeds"
                                :key="breed.id"
                                :value="breed.id"
                            >
                                {{ breed.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.breed" />
                </div>

                <div class="space-y-2">
                    <Label for="age" is-required>Age (years)</Label>
                    <Input
                        id="age"
                        v-model="form.age"
                        type="number"
                        min="0"
                        step="0.1"
                        required
                    />
                    <InputError :message="form.errors.age" />
                </div>

                <div class="space-y-2">
                    <Label for="color" is-required>Pet Color</Label>
                    <Input
                        id="color"
                        v-model="form.color"
                        required
                        placeholder="Ex: Gray with white color"
                    />
                    <InputError :message="form.errors.color" />
                </div>

                <div class="space-y-2">
                    <Label is-required>Gender</Label>
                    <div class="flex space-x-4">
                        <div class="flex items-center space-x-2">
                            <input
                                id="male"
                                v-model="form.gender"
                                type="radio"
                                value="male"
                                class="h-4 w-4"
                                required
                            />
                            <Label for="male">Male</Label>
                        </div>

                        <div class="flex items-center space-x-2">
                            <input
                                id="female"
                                v-model="form.gender"
                                type="radio"
                                value="female"
                                class="h-4 w-4"
                            />
                            <Label for="female">Female</Label>
                        </div>
                    </div>
                    <InputError :message="form.errors.gender" />
                </div>

                <div class="space-y-2">
                    <Label is-required>Listing Type</Label>
                    <div
                        class="flex flex-col space-y-2 sm:flex-row sm:space-x-4 sm:space-y-0"
                    >
                        <div
                            v-for="type in listingTypes"
                            :key="type.value"
                            class="flex items-center space-x-2"
                        >
                            <input
                                :id="`type-${type.value}`"
                                v-model="form.listing_type"
                                type="radio"
                                :value="type.value"
                                class="h-4 w-4"
                            />
                            <Label :for="`type-${type.value}`">{{
                                type.label
                            }}</Label>
                        </div>
                    </div>
                    <InputError :message="form.errors.listing_type" />
                </div>

                <div class="space-y-2">
                    <Label for="price">Price ($)</Label>
                    <Input
                        id="price"
                        v-model="form.price"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                        :disabled="form.listing_type !== 2"
                        :class="{
                            'cursor-not-allowed opacity-50':
                                form.listing_type !== 2,
                        }"
                    />
                    <InputError :message="form.errors.price" />
                </div>

                <div class="space-y-2">
                    <Label for="status">Status</Label>
                    <Select v-model="form.status" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="available">Available</SelectItem>
                            <SelectItem value="adopted">Adopted</SelectItem>
                            <SelectItem value="sold">Sold</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
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
[class*='transition'],
[class*='transform'],
[class*='scale'] {
    will-change: transform;
    transform: translateZ(0);
    -webkit-transform: translateZ(0);
}
</style>
