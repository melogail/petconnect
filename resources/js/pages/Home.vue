<script setup lang="ts">
import Filter from '@/components/web/Filter.vue';
import PetCard from '@/components/web/PetCard.vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { Plus } from 'lucide-vue-next';

const petData = {
    name: 'Bella',
    age: '1 Year',
    gender: 0,
    location: 'Perth, Australia',
    description: 'Affectionate cat, loves to be around people.',
    image: 'https://placekittens.com/400/400',
    status: 'adoption',
    isFavorite: false,
    vaccinated: true,
    serialized: true,
    likes: 34,
    comments: 10,
};
</script>

<template>
    <MainLayout>
        <div class="content grid grid-cols-12">
            <!-- Filter -->
            <div
                class="col-span-12 border-r border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 lg:col-span-3 transition-colors duration-200"
            >
                <Filter />
            </div>

            <!-- Cards -->
            <div
                class="col-span-12 bg-white dark:bg-gray-900 px-6 py-4 lg:col-span-9 transition-colors duration-200"
            >
                <section
                    id="petsCard"
                    class="flex items-center justify-between my-8"
                >
                    <h2 class="text-2xl font-bold text-primary dark:text-primary-400">
                        Discover Pets
                    </h2>
                    <!-- Create post button -->
                    <button class="flex items-center gap-2 px-12 cursor-pointer bg-gradient-to-r from-violet-500 to-fuchsia-500 text-white font-semibold py-3 rounded-full hover:from-violet-600 hover:to-fuchsia-600 transition-colors shadow-lg">
                        <Plus class="h-5 w-5" />
                        Create Post
                    </button>
                </section>
                <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <PetCard :pet="petData" />
                    <PetCard :pet="petData" />
                    <PetCard :pet="petData" />
                </section>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped></style>
