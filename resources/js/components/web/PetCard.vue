<template>
    <article
        class="pet-card group w-[300px] cursor-pointer overflow-hidden rounded-2xl border border-gray-200 bg-white transition-all duration-300 hover:shadow-2xl hover:border-primary-500"
        itemscope
        itemtype="https://schema.org/Pet"
    >
        <!-- Pet Image -->
        <figure
            class="relative h-[280px] overflow-hidden"
            itemprop="image"
            itemscope
        >
            <img
                :src="pet.image"
                :alt="`Photo of ${pet.name}, a ${pet.age} ${pet.gender.toLowerCase()} from ${pet.location}`"
                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                loading="lazy"
            />

            <!-- Overlay -->
            <div
                class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
            ></div>

            <!-- Favorite Button -->
            <button
                class="absolute top-4 left-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-md transition-colors hover:bg-gray-50 focus:ring-2 focus:ring-primary focus:outline-none"
                :aria-pressed="pet.isFavorite"
                :aria-label="
                    pet.isFavorite
                        ? 'Remove from favorites'
                        : 'Add to favorites'
                "
                @click.stop="toggleFavorite"
            >
                <svg
                    :class="[
                        'h-5 w-5 transition-colors',
                        pet.isFavorite
                            ? 'fill-red-500 stroke-red-500'
                            : 'fill-none stroke-gray-600'
                    ]"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
            </button>

            <!-- For Sale Badge -->
            <div
                v-if="pet.forSale"
                class="absolute top-4 right-4 z-10 rounded-full bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white"
            >
                For Sale
            </div>

            <!-- Status Icons with Tooltips -->
            <TooltipProvider>
                <div class="absolute right-4 bottom-4 flex items-center gap-2">
                    <!-- Vaccinated -->
                    <Tooltip v-if="pet.vaccinated">
                        <TooltipTrigger class="flex h-8 w-8 items-center justify-center rounded-full bg-white/80 p-1.5 text-green-600 shadow-sm transition-colors hover:bg-white">
                            <Syringe class="h-4 w-4" />
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Vaccinated</p>
                        </TooltipContent>
                    </Tooltip>

                    <!-- Serialized -->
                    <Tooltip v-if="pet.serialized">
                        <TooltipTrigger class="flex h-8 w-8 items-center justify-center rounded-full bg-white/80 p-1.5 text-blue-600 shadow-sm transition-colors hover:bg-white">
                            <HeartCrack  class="h-4 w-4" />
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Serialized</p>
                        </TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
        </figure>

        <!-- Card Content -->
        <div class="p-5">
            <!-- Pet Name -->
            <h3 class="text-lg font-bold text-gray-800 mb-2" itemprop="name">{{ pet.name }}</h3>

            <!-- Pet Details -->
            <div class="flex items-center gap-4 text-sm text-gray-600 mb-3">
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path d="M12 6v6l4 2" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span itemprop="age">{{ pet.age }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path d="M12 2a10 10 0 0 1 10 10c0 6-10 12-10 12S2 18 2 12A10 10 0 0 1 12 2z" stroke-width="2"/>
                        <circle cx="12" cy="11" r="3" stroke-width="2"/>
                    </svg>
                    <span itemprop="gender">{{ pet.gender }}</span>
                </div>
            </div>

            <!-- Location -->
            <div class="flex items-center gap-1.5 text-sm text-gray-600 mb-3">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke-width="2"/>
                    <circle cx="12" cy="10" r="3" stroke-width="2"/>
                </svg>
                <span itemprop="address">{{ pet.location }}</span>
            </div>

            <!-- Description -->
            <p class="text-sm text-gray-600 mb-4 line-clamp-2" itemprop="description">{{ pet.description }}</p>

            <!-- Divider -->
            <div class="my-4 border-t border-gray-300"></div>

            <!-- Action Buttons -->
            <div class="mb-4 flex items-center justify-between text-gray-500">
                <!-- Likes -->
                <button class="group flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-colors hover:bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:fill-red-500 group-hover:text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                    <span class="text-sm font-medium">{{ pet.likes || 0 }}</span>
                </button>

                <!-- Comments -->
                <button class="group flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-colors hover:bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span class="text-sm font-medium">{{ pet.comments || 0 }}</span>
                </button>

                <!-- Share -->
                <button
                    @click.stop="$emit('share', pet)"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-colors hover:bg-gray-100 hover:text-violet-500"
                    aria-label="Share"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                    <span class="text-sm font-medium">Share</span>
                </button>
            </div>

            <!-- Contact Button -->
            <button
                @click="$emit('contact')"
                class="w-full rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-500 py-3 text-center font-semibold text-white transition hover:opacity-90"
            >
                Contact Owner
            </button>
        </div>
    </article>
</template>

<script setup>
import { HeartCrack, Syringe, MessageSquare, Share2, Heart } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const props = defineProps({
    pet: {
        type: Object,
        required: true,
        default: () => ({
            name: 'Bella',
            age: '1 Year',
            gender: 'Female',
            location: 'Perth, Australia',
            description: 'Affectionate cat, loves to be around people.',
            image: 'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=400&h=400&fit=crop',
            forSale: true,
            isFavorite: false,
            vaccinated: true,
            serialized: false,
            likes: 34,
            comments: 10,
        }),
    },
});

const toggleFavorite = () => {
    props.pet.isFavorite = !props.pet.isFavorite;
};
</script>

<style scoped>
/* Custom tooltip styling */
:deep(.tooltip-content) {
    z-index: 50;
    overflow: hidden;
    border-radius: 0.375rem;
    background-color: #111827; /* bg-gray-900 */
    padding: 0.375rem 0.75rem; /* px-3 py-1.5 */
    font-size: 0.75rem; /* text-xs */
    line-height: 1rem;
    color: white;
    animation: tooltip-fade-in 0.2s ease-out;
}

@keyframes tooltip-fade-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
