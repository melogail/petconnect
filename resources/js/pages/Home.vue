<script setup lang="ts">
import PetCard from '@/components/web/PetCard.vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { Link } from '@inertiajs/vue3';
import { Plus, Filter as FilterIcon } from 'lucide-vue-next';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import Filter from '@/components/web/Filter.vue';
import { route } from 'ziggy-js';
import { useInertiaInfiniteScroll } from '@/composables/useInertiaInfiniteScroll';
import InfiniteScroll from '@/components/web/InfiniteScroll.vue';
import { useAuthUser } from '@/composables/useAuthUser';

const user = useAuthUser();
const props = defineProps<{
    pets: any;
    reportReasons: Array<{ value: string; label: string }>;
}>();

const {
    items: allPets,
    nextUrl,
    isLoading,
    loadMore,
} = useInertiaInfiniteScroll<any>(props.pets, 'pets');
</script>

<template>
    <MainLayout>
        <div class="mx-auto w-full max-w-7xl px-6 py-8">
            <!-- Filter Button for All Devices -->
            <div class="mb-6">
                <Sheet>
                    <SheetTrigger as-child>
                        <Button variant="outline" class="gap-2">
                            <FilterIcon class="h-4 w-4" />
                            <span>Filters</span>
                        </Button>
                    </SheetTrigger>
                    <SheetContent
                        side="left"
                        class="w-[350px] p-0 sm:w-[400px]"
                    >
                        <div class="h-full overflow-y-auto">
                            <Filter />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
            <div class="w-full">
                <div
                    class="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
                >
                    <h2
                        class="text-2xl font-bold text-primary dark:text-primary-400"
                    >
                        Discover Pets
                    </h2>
                    <Link
                        v-if="user?.email_verified_at"
                        :href="route('pets.create')"
                        :class="
                            cn(
                                buttonVariants(),
                                'cursor-pointer gap-2 bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:from-violet-600 hover:to-fuchsia-600',
                            )
                        "
                    >
                        <Plus class="h-5 w-5" />
                        Create Post
                    </Link>
                </div>
                <section
                    class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <template v-if="allPets.length > 0">
                        <PetCard
                            v-for="(pet, index) in allPets"
                            :key="index"
                            :pet="pet"
                            :report-reasons="reportReasons"
                        />
                    </template>
                    <div
                        v-else
                        class="col-span-full py-12 text-center text-gray-500"
                    >
                        <p>No pets found</p>
                    </div>
                </section>

                <!-- Infinite Scroll Component -->
                <InfiniteScroll
                    v-if="allPets.length > 0"
                    :has-more="!!nextUrl"
                    :is-loading="isLoading"
                    @load-more="loadMore"
                />
            </div>
        </div>
    </MainLayout>
</template>

<style scoped></style>
