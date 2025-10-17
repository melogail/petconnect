<script setup lang="ts">
import Filter from '@/components/web/Filter.vue';
import PetCard from '@/components/web/PetCard.vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { Plus } from 'lucide-vue-next';

const petData = {
    name: 'Bella',
    age: '1 Year',
    gender: 1,
    location: 'Perth, Australia',
    description: 'Affectionate cat, loves to be around people.',
    image: 'https://placekittens.com/400/400',
    forSale: true,
    isFavorite: true,
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
                class="col-span-12 border-r border-b border-gray-200 bg-white lg:col-span-3"
            >
                <Filter />
            </div>

            <!-- Cards -->
            <div
                class="col-span-12 bg-white px-6 py-4 lg:col-span-9"
            >
                <section
                    id="petsCard"
                    class="flex items-center justify-between my-8"
                >
                    <h2 class="text-2xl font-bold text-primary">
                        Discover Pets
                    </h2>
                    <!-- Create post button -->

                    <button class="flex items-center gap-2 px-12 cursor-pointer bg-gradient-to-r from-violet-500 to-fuchsia-500  text-white font-semibold py-3 rounded-full hover:bg-purple-700 transition-colors shadow-lg">
                        <Plus class="mr-2 h-5 w-5" />
                        Create Post
                    </button>
                </section>
                <section class="grid grid-cols-3 gap-4">
                    <PetCard :pet="petData" />
                    <PetCard :pet="petData" />
                    <PetCard :pet="petData" />
                </section>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped></style>
