<template>
    <article
        class="pet-card group relative w-full max-w-[320px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-primary-400/50 dark:border-gray-700/60 dark:bg-gray-800/80 dark:hover:border-primary-500/50 hover:shadow-primary-100 dark:hover:shadow-primary-900/20"
        itemscope
        itemtype="https://schema.org/Pet"
    >
        <!-- Pet Image with Gradient Overlay -->
        <figure
            class="relative h-64 overflow-hidden bg-gray-100 dark:bg-gray-700/50"
            itemprop="image"
            itemscope
        >
            <!-- Enhanced gradient overlay with better transition -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent z-[1] opacity-0 group-hover:opacity-100 transition-opacity duration-500 ease-in-out"></div>
            <div class="h-full w-full overflow-hidden">
                <img
                    :src="pet.image"
                    :alt="`Photo of ${pet.name}, a ${pet.age} ${pet.gender === 1 ? 'Male' : 'Female'} from ${pet.location}`"
                    class="h-full w-full object-cover transition-all duration-700 group-hover:scale-110"
                    loading="lazy"
                    @error="$event.target.src = '/images/pet-placeholder.jpg'"
                    :style="{'view-transition-name': `pet-image-${pet.id || 'default'}`}"
                />
            </div>

            <!-- Overlay -->

            <!-- Favorite Button with Animation -->
            <button
                class="absolute top-4 left-4 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 shadow-md backdrop-blur-sm transition-all duration-300 hover:scale-110 hover:bg-white hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:bg-gray-800/80 dark:hover:bg-gray-700/90"
                :aria-pressed="pet.isFavorite"
                :aria-label="pet.isFavorite ? 'Remove from favorites' : 'Add to favorites'"
                @click.stop="toggleFavorite"
            >
                <svg
                    :class="[
                        'h-4.5 w-4.5 transition-all duration-300',
                        pet.isFavorite
                            ? 'fill-red-500 stroke-red-500 scale-125'
                            : 'fill-white/0 stroke-gray-600 group-hover:stroke-gray-700 dark:stroke-gray-300 dark:group-hover:stroke-gray-200'
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

            <!-- Status Badge -->
            <div
                v-if="pet.status === 'sale' || pet.status === 'adoption'"
                class="absolute top-4 right-4 z-10 group"
            >
                <div class="relative">
                    <!-- Animated background glow -->
                    <div
                        class="absolute -inset-1 rounded-full opacity-75 group-hover:opacity-100 blur transition-all duration-500 animate-pulse group-hover:animate-none"
                        :class="{
                            'bg-gradient-to-r from-amber-400 to-rose-500': pet.status === 'sale',
                            'bg-gradient-to-r from-teal-400 to-emerald-500': pet.status === 'adoption'
                        }"
                    ></div>

                    <!-- Main badge -->
                    <div
                        class="relative flex items-center px-3 py-1 rounded-full text-xs font-bold text-white shadow-lg transition-all duration-300 group-hover:scale-105 group-hover:shadow-xl"
                        :class="{
                            'bg-gradient-to-r from-amber-400 to-rose-500 shadow-amber-500/30 group-hover:shadow-amber-500/40': pet.status === 'sale',
                            'bg-gradient-to-r from-teal-400 to-emerald-500 shadow-teal-500/30 group-hover:shadow-teal-500/40': pet.status === 'adoption'
                        }"
                    >
                        <span class="text-white/95 font-semibold tracking-wide">
                            {{ pet.status === 'sale' ? 'For Sale' : 'For Adoption' }}
                        </span>

                        <!-- Subtle shine effect on hover -->
                        <span class="absolute inset-0 overflow-hidden rounded-full">
                            <span class="absolute top-0 left-0 w-1/2 h-full bg-white/20 transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-500 ease-in-out"></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Status Icons with Tooltips -->
            <!-- Enhanced status bar with better visibility -->
            <div class="absolute bottom-0 left-0 right-0 z-10 bg-gradient-to-t from-black/80 via-black/50 to-transparent p-4 pt-10 opacity-0 transition-all duration-500 ease-out group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0">
                <TooltipProvider>
                    <div class="flex items-center justify-center gap-3">
                        <!-- Vaccinated -->
                        <Tooltip v-if="pet.vaccinated">
                            <TooltipTrigger class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 p-1.5 text-green-600 shadow-sm backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white">
                                <Syringe class="h-4 w-4" />
                            </TooltipTrigger>
                            <TooltipContent class="bg-gray-800 text-white border-0 text-xs">
                                <p>Vaccinated</p>
                            </TooltipContent>
                        </Tooltip>

                        <!-- Serialized -->
                        <Tooltip v-if="pet.serialized">
                            <TooltipTrigger class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 p-1.5 text-blue-600 shadow-sm backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white">
                                <HeartCrack class="h-4 w-4" />
                            </TooltipTrigger>
                            <TooltipContent class="bg-gray-800 text-white border-0 text-xs">
                                <p>Microchipped</p>
                            </TooltipContent>
                        </Tooltip>

                        <!-- Age -->
                        <Tooltip>
                            <TooltipTrigger class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 p-1.5 text-amber-600 shadow-sm backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </TooltipTrigger>
                            <TooltipContent class="bg-gray-800 text-white border-0 text-xs">
                                <p>{{ pet.age }} old</p>
                            </TooltipContent>
                        </Tooltip>

                        <!-- Location -->
                        <Tooltip>
                            <TooltipTrigger class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 p-1.5 text-purple-600 shadow-sm backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </TooltipTrigger>
                            <TooltipContent class="bg-gray-800 text-white border-0 text-xs">
                                <p>Located in {{ pet.location }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </div>
        </figure>

        <!-- Card Content with subtle animation on hover -->
        <div class="p-5 pt-4 transition-all duration-300 group-hover:bg-white/95 dark:group-hover:bg-gray-800/95">
            <!-- Pet Name and Gender with enhanced typography -->
            <div class="flex items-center justify-between mb-2.5">
                <h3
                    class="text-xl font-extrabold text-gray-900 dark:text-white truncate pr-2 transition-colors duration-300 group-hover:text-primary-600 dark:group-hover:text-primary-400"
                    itemprop="name"
                >
                    {{ pet.name }}
                </h3>
                <span class="flex-shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                      :class="pet.gender === 1
                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
                        : 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300'">
                    <svg v-if="pet.gender === 1" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1">
                        <path d="M16 3h5v5"/>
                        <path d="m21 3-6.75 6.75"/>
                        <circle cx="10" cy="14" r="6"/>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1">
                        <path d="M12 15v7"/>
                        <path d="M9 19h6"/>
                        <circle cx="12" cy="9" r="6"/>
                    </svg>
                    {{ pet.gender === 1 ? 'Male' : 'Female' }}
                </span>
            </div>

            <!-- Breed and Age -->
            <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400 mb-3">
                <div class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ pet.breed || 'Mixed Breed' }}</span>
                </div>
                <div class="h-1 w-1 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                <div class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300" itemprop="age">{{ pet.age }}</span>
                </div>
            </div>

            <!-- Enhanced description with smoother reveal -->
            <div class="relative mb-4">
                <div class="relative overflow-hidden">
                    <p
                        class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed transition-all duration-500 ease-in-out"
                        :class="showFullDescription ? 'max-h-[500px]' : 'max-h-[4.5rem] overflow-hidden'"
                        itemprop="description"
                    >
                        {{ pet.description || 'No description available for this pet.' }}
                    </p>
                    <div v-if="pet.description && pet.description.length > 120" class="absolute bottom-0 left-0 right-0 h-10 bg-gradient-to-t from-white to-transparent dark:from-gray-800 dark:to-transparent flex items-end justify-center pb-1" v-show="!showFullDescription">
                        <button
                            @click.stop="showFullDescription = true"
                            class="text-xs font-medium bg-white dark:bg-gray-800 px-2 py-0.5 rounded-full text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 focus:outline-none border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow transition-all"
                        >
                            Read more
                        </button>
                    </div>
                </div>
                <button
                    v-if="showFullDescription && pet.description && pet.description.length > 120"
                    @click.stop="showFullDescription = false"
                    class="mt-2 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 focus:outline-none flex items-center mx-auto transition-all duration-200 hover:underline"
                >
                    Show less
                    <svg class="ml-1 w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>

            <!-- Divider with Pattern -->
            <div class="relative my-4">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-2 text-xs text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mb-4 flex items-center justify-between text-gray-500 dark:text-gray-400">
                <!-- Likes with Animation -->
                <button
                    @click.stop="toggleLike"
                    class="group relative flex items-center gap-1.5 overflow-hidden rounded-full px-3 py-1.5 text-sm transition-all duration-300 hover:bg-gray-100 dark:hover:bg-gray-700/50"
                    :class="{ 'text-red-500': isLiked }"
                >
                    <span class="relative">
                        <Heart
                            class="h-5 w-5 transition-all duration-300"
                            :class="{
                                'fill-red-500 text-red-500 scale-110': isLiked,
                                'group-hover:fill-red-500/20 group-hover:text-red-500': !isLiked
                            }"
                        />
                        <transition
                            enter-active-class="transition-all duration-300 ease-out"
                            leave-active-class="transition-all duration-300 ease-in absolute inset-0"
                            enter-from-class="transform scale-0 opacity-0"
                            enter-to-class="transform scale-100 opacity-100"
                            leave-from-class="transform scale-100 opacity-100"
                            leave-to-class="transform scale-150 opacity-0"
                        >
                            <Heart
                                v-if="isLiked"
                                class="absolute inset-0 h-5 w-5 fill-red-500 text-red-500"
                            />
                        </transition>
                    </span>
                    <span class="text-sm font-medium transition-colors duration-300">
                        {{ likeCount }}
                    </span>
                </button>

                <!-- Comments with Animation -->
                <div class="group relative">
                    <CommentsDialog
                        :pet-id="pet.id"
                        :pet-name="pet.name"
                        :comments-count="pet.comments || 0"
                        :current-user-id="user?.id"
                        @comment-added="handleCommentAdded"
                        @comment-deleted="handleCommentDeleted"
                    >
                        <template #trigger="{ open }">
                            <button
                                @click.stop="open"
                                class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-all duration-300 hover:bg-gray-100 hover:text-primary-500 dark:hover:bg-gray-700/50 dark:hover:text-primary-400"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <span class="text-sm font-medium">{{ pet.comments || 0 }}</span>
                            </button>
                        </template>
                    </CommentsDialog>
                    <div class="absolute -bottom-1 left-1/2 h-0.5 w-0 -translate-x-1/2 bg-primary-500 transition-all duration-300 group-hover:w-4/5"></div>
                </div>

                <!-- Share Dropdown with Animation -->
                <div class="group relative">
                    <button
                        @click.stop="toggleShareDropdown"
                        class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-all duration-300 hover:bg-gray-100 hover:text-primary-500 dark:hover:bg-gray-700/50 dark:hover:text-primary-400"
                        aria-label="Share"
                        aria-haspopup="true"
                        :aria-expanded="isShareOpen"
                        ref="shareButton"
                    >
                        <Share2 class="h-5 w-5" />
                    </button>
                    <div class="absolute -bottom-1 left-1/2 h-0.5 w-0 -translate-x-1/2 bg-primary-500 transition-all duration-300 group-hover:w-4/5"></div>

                    <!-- Animated Dropdown -->
                    <transition name="fade-slide">
                        <div
                            v-if="isShareOpen"
                            class="absolute right-0 z-10 mt-2 flex gap-3 rounded-lg bg-white dark:bg-gray-800 px-4 py-3 shadow-lg ring-1 ring-black/5 dark:ring-gray-700"
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
            <div class="flex items-center gap-2 mt-5">
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
import { ref, onMounted, onUnmounted } from 'vue';
import { HeartCrack, Syringe, Share2, Heart, Copy, Mail, MessageCircle } from 'lucide-vue-next';
import CommentsDialog from './CommentsDialog.vue';
import QuickMessageDialog from './QuickMessageDialog.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

// Define props
const props = defineProps({
    pet: {
        type: Object,
        required: true,
        default: () => ({
            name: '',
            age: '',
            gender: 1,
            location: '',
            description: '',
            image: '',
            status: '',
            isFavorite: false,
            vaccinated: false,
            serialized: false,
            likes: 0,
            comments: 0,
            id: ''
        })
    },
    user: {
        type: Object,
        default: null
    }
});

// Define emits
const emit = defineEmits([
    'favorite-toggle',
    'comment-added',
    'comment-deleted',
    'message-sent',
    'view-details'
]);

// Component state
const showFullDescription = ref(false);
const isLiked = ref(false);
const likeCount = ref(props.pet?.likes || 0);
const shareButton = ref(null);
const isShareOpen = ref(false);
const showMessageDialog = ref(false);
const commentsCount = ref(props.pet?.comments || 0);
const showComments = ref(false);

// Toggle like with animation
const toggleLike = () => {
    isLiked.value = !isLiked.value;
    likeCount.value += isLiked.value ? 1 : -1;
    // Here you would typically make an API call to update the like status
};

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

const toggleFavorite = () => {
    if (props.pet) {
        props.pet.isFavorite = !props.pet.isFavorite;
        emit('favorite-toggle', props.pet.isFavorite);
    }
};

// Event handlers
const handleCommentAdded = (comment) => {
    if (props.pet) {
        if (!props.pet.comments) {
            props.pet.comments = 0;
        }
        props.pet.comments++;
        emit('comment-added', comment);
    }
};

const handleCommentDeleted = (commentId) => {
    if (props.pet?.comments > 0) {
        props.pet.comments--;
        emit('comment-deleted', commentId);
    }
};

const handleMessageSent = () => {
    showMessageDialog.value = false;
    emit('message-sent');
};

// Lifecycle hooks
onMounted(() => {
    document.addEventListener('click', closeShareDropdown);
    document.addEventListener('keydown', (e) => e.key === 'Escape' && (isShareOpen.value = false));
});

onUnmounted(() => {
    document.removeEventListener('click', closeShareDropdown);
    document.removeEventListener('keydown', (e) => e.key === 'Escape' && (isShareOpen.value = false));
});



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
