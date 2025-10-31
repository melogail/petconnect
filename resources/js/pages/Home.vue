<script setup lang="ts">
import PetCard from '@/components/web/PetCard.vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { Plus, Filter as FilterIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet';
import Filter from '@/components/web/Filter.vue';

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
        <div class="max-w-7xl mx-auto w-full px-6 py-8">
            <!-- Filter Button for All Devices -->
            <div class="mb-6">
                <Sheet>
                    <SheetTrigger as-child>
                        <Button variant="outline" class="gap-2">
                            <FilterIcon class="h-4 w-4" />
                            <span>Filters</span>
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" class="w-[350px] sm:w-[400px] p-0">
                        <div class="h-full overflow-y-auto">
                            <Filter />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
            <div class="w-full">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-4">
                    <h2 class="text-2xl font-bold text-primary dark:text-primary-400">
                        Discover Pets
                    </h2>
                    <Button class="gap-2 bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:from-violet-600 hover:to-fuchsia-600">
                        <Plus class="h-5 w-5" />
                        Create Post
                    </Button>
                </div>
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <PetCard v-for="(pet, index) in Array(12).fill(petData)" :key="index" :pet="pet" />
                </section>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped></style>
