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
                :alt="`Photo of ${pet.name}, a ${pet.age} ${pet.gender === 1 ? 'Male' : 'Female'} from ${pet.location}`"
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
                :aria-label="pet.isFavorite ? 'Remove from favorites' : 'Add to favorites'"
                @click.stop="toggleFavorite"
            >
                <svg
                    :class="[
                        'h-5 w-5 transition-colors',
                        pet.isFavorite ? 'fill-red-500 stroke-red-500' : 'fill-none stroke-gray-600'
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
                        <TooltipContent><p>Vaccinated</p></TooltipContent>
                    </Tooltip>

                    <!-- Serialized -->
                    <Tooltip v-if="pet.serialized">
                        <TooltipTrigger class="flex h-8 w-8 items-center justify-center rounded-full bg-white/80 p-1.5 text-blue-600 shadow-sm transition-colors hover:bg-white">
                            <HeartCrack class="h-4 w-4" />
                        </TooltipTrigger>
                        <TooltipContent><p>Serialized</p></TooltipContent>
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
                <div class="flex items-center gap-0.5">
                    <svg v-if="pet.gender === 1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mars-icon lucide-mars"><path d="M16 3h5v5"/><path d="m21 3-6.75 6.75"/><circle cx="10" cy="14" r="6"/></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-venus-icon lucide-venus"><path d="M12 15v7"/><path d="M9 19h6"/><circle cx="12" cy="9" r="6"/></svg>
                    <span itemprop="gender">{{ pet.gender === 1 ? "Male" : "Female" }}</span>
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
                    <Heart class="h-5 w-5 group-hover:fill-red-500 group-hover:text-red-500" />
                    <span class="text-sm font-medium">{{ pet.likes || 0 }}</span>
                </button>

                <!-- Comments -->
                <CommentsDialog 
                  :pet-id="pet.id" 
                  :pet-name="pet.name" 
                  :comments-count="pet.comments || 0"
                  :current-user-id="user?.id"
                  @comment-added="handleCommentAdded"
                  @comment-deleted="handleCommentDeleted"
                />

                <!-- Share Dropdown -->
                <div class="relative">
                    <button
                        @click.stop="toggleShareDropdown"
                        class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-colors hover:bg-gray-100 hover:text-violet-500"
                        aria-label="Share"
                        aria-haspopup="true"
                        :aria-expanded="isShareOpen"
                        ref="shareButton"
                    >
                        <Share2 class="h-5 w-5" />
                    </button>

                    <!-- Animated Dropdown -->
                    <transition name="fade-slide">
                        <div
                            v-if="isShareOpen"
                            class="absolute right-0 z-10 mt-2 flex gap-3 rounded-lg bg-white px-4 py-3 shadow-lg ring-1 ring-black/5"
                            role="menu"
                            aria-orientation="horizontal"
                        >
                            <TooltipProvider>
                                <!-- Facebook -->
                                <Tooltip>
                                    <TooltipTrigger>
                                        <button @click="shareOnSocial('facebook')" class="text-[#1877F2] hover:scale-110 transition-transform">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/>
                                            </svg>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>Facebook</TooltipContent>
                                </Tooltip>

                                <!-- Twitter -->
                                <Tooltip>
                                    <TooltipTrigger>
                                        <button
                                            @click="shareOnSocial('twitter')"
                                            class=" hover:scale-110 transition-transform"
                                            aria-label="Share on Twitter"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                class="w-5 h-5"
                                                fill="currentColor"
                                                role="img"
                                                aria-label="Warning Icon"
                                            >
                                                <!-- Outer dark triangle -->
                                                <polygon
                                                    fill="currentColor"
                                                    fill-opacity="0.9"
                                                    points="20.5,3 4.964,21 3.107,21 18.643,3"
                                                />
                                                <!-- Inner white triangle -->
                                                <polygon
                                                    fill="white"
                                                    fill-rule="evenodd"
                                                    points="15.571,20.5 3.91,3.5 8.388,3.5 20.05,20.5"
                                                    clip-rule="evenodd"
                                                />
                                                <!-- Outline -->
                                                <path
                                                    fill="currentColor"
                                                    d="M7.862,4.5l10.289,15h-2.053L5.809,4.5H7.862 M8.652,3H2.961l12.347,18h5.691L8.652,3L8.652,3z"
                                                />
                                            </svg>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>X (Twitter)</TooltipContent>
                                </Tooltip>

                                <!-- WhatsApp -->
                                <Tooltip>
                                    <TooltipTrigger>
                                        <button @click="shareOnSocial('whatsapp')" class="text-[#25D366] hover:scale-110 transition-transform">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                class="w-5 h-5"
                                                fill="currentColor"
                                                role="img"
                                                aria-label="WhatsApp Icon"
                                            >
                                                <path
                                                    d="M12.04 2c-5.52 0-10 4.39-10 9.8 0 1.73.47 3.42 1.36 4.91L2 22l5.45-1.43a9.96 9.96 0 0 0 4.59 1.12h.01c5.52 0 10-4.39 10-9.8 0-2.62-1.07-5.08-3.01-6.95A10.3 10.3 0 0 0 12.04 2zm0 17.83h-.01a8.38 8.38 0 0 1-4.28-1.17l-.31-.18-3.23.85.86-3.12-.2-.32a8.17 8.17 0 0 1-1.27-4.38c0-4.49 3.77-8.15 8.43-8.15 2.25 0 4.36.86 5.95 2.42a8.03 8.03 0 0 1 2.46 5.84c0 4.5-3.77 8.16-8.4 8.16z"
                                                />
                                                <path
                                                    fill="currentColor"
                                                    d="M17.26 14.59c-.27-.14-1.61-.79-1.86-.88-.25-.09-.43-.14-.61.14-.18.27-.7.87-.86 1.05-.16.18-.32.2-.59.07-.27-.14-1.14-.42-2.17-1.34-.8-.71-1.34-1.59-1.5-1.86-.16-.27-.02-.41.12-.55.13-.13.27-.34.4-.5.13-.16.18-.27.27-.45.09-.18.05-.34-.02-.48-.07-.14-.61-1.46-.84-2-.22-.53-.44-.46-.61-.46h-.52c-.18 0-.48.07-.73.34-.25.27-.96.94-.96 2.29s.99 2.65 1.13 2.84c.14.18 1.93 3.05 4.69 4.27.65.28 1.15.45 1.54.57.65.21 1.24.18 1.7.11.52-.08 1.61-.66 1.84-1.3.23-.64.23-1.19.16-1.3-.07-.11-.25-.18-.52-.32z"
                                                />
                                            </svg>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>WhatsApp</TooltipContent>
                                </Tooltip>

                                <!-- Copy link -->
                                <Tooltip>
                                    <TooltipTrigger>
                                        <button @click="copyToClipboard" class="text-gray-500 hover:scale-110 transition-transform">
                                            <Copy class="h-5 w-5" />
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>Copy link</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </transition>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3 mt-4">
                <!-- Quick Message Dialog Trigger -->
                <QuickMessageDialog 
                    v-model:open="showMessageDialog"
                    :owner-name="pet.ownerName || 'the owner'" 
                    :pet-name="pet.name"
                    :pet-id="pet.id"
                    @message-sent="handleMessageSent"
                >
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <button
                                    @click="showMessageDialog = true"
                                    class="flex-shrink-0 w-12 h-12 rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-500 flex items-center justify-center text-white transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
                                    aria-label="Contact Owner"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                        <rect width="20" height="16" x="2" y="4" rx="2" />
                                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                                    </svg>
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>Contact Owner</TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </QuickMessageDialog>
                
                <!-- View Details Button -->
                <button
                    @click="$emit('view-details')"
                    class="flex-1 h-12 rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-500 text-center font-semibold text-white transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
                >
                    View Details
                </button>
            </div>
        </div>
    </article>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { HeartCrack, Syringe, Share2, Heart, Copy, Mail } from 'lucide-vue-next';
import CommentsDialog from './CommentsDialog.vue';
import QuickMessageDialog from './QuickMessageDialog.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const shareButton = ref(null);
const isShareOpen = ref(false);
const showMessageDialog = ref(false);

const toggleShareDropdown = (event) => {
    event.stopPropagation();
    isShareOpen.value = !isShareOpen.value;
};

const closeShareDropdown = (event) => {
    if (shareButton.value && !shareButton.value.contains(event.target)) {
        isShareOpen.value = false;
    }
};

const shareOnSocial = (platform) => {
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent(`Check out ${props.pet.name} on PetConnect`);
    let shareUrl = '';
    switch (platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
            break;
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${title}%20${url}`;
            break;
    }
    window.open(shareUrl, '_blank', 'width=600,height=400');
    isShareOpen.value = false;
};

const copyToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(window.location.href);
        console.log('Link copied to clipboard');
        isShareOpen.value = false;
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
};

onMounted(() => {
    document.addEventListener('click', closeShareDropdown);
    document.addEventListener('keydown', (e) => e.key === 'Escape' && (isShareOpen.value = false));
});
onUnmounted(() => document.removeEventListener('click', closeShareDropdown));

const props = defineProps({
    pet: {
        type: Object,
        required: true,
    },
    user: {
        type: Object,
        default: null,
    },
    onViewDetails: {
        type: Function,
        default: () => {}
    },
});

const toggleFavorite = () => (props.pet.isFavorite = !props.pet.isFavorite);

const handleCommentAdded = (comment) => {
    // Increment comments count
    if (!props.pet.comments) {
        props.pet.comments = 0;
    }
    props.pet.comments++;
};

const handleCommentDeleted = (commentId) => {
    // Decrement comments count
    if (props.pet.comments > 0) {
        props.pet.comments--;
    }
};

const handleMessageSent = () => {
    // You can add any additional logic here when a message is sent
    // For example, show a success toast or update UI
    console.log('Message sent successfully');
};
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.25s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

:deep(.tooltip-content) {
    z-index: 50;
    overflow: hidden;
    border-radius: 0.375rem;
    background-color: #111827;
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
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
