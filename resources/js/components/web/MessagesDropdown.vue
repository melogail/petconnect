<script setup lang="ts">
import { TransitionGroup } from 'vue';

defineProps<{
    messages: Array<{ id: number; sender: { name: string; avatar: string }; preview: string; time: string; read: boolean }>;
    unreadCount: number;
    isOpen: boolean;
}>();

const emit = defineEmits(['toggle', 'markAsRead']);
const markAsRead = (id: number) => emit('markAsRead', id);
</script>

<template>
    <div class="relative">
        <button 
            @click.stop="emit('toggle')" 
            class="relative p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
            :aria-expanded="isOpen"
            :aria-label="`Messages ${unreadCount > 0 ? `(${unreadCount} unread)` : ''}`"
        >
            <span class="sr-only">Messages</span>
            <svg 
                xmlns="http://www.w3.org/2000/svg" 
                class="h-6 w-6 text-gray-600 dark:text-gray-300 transition-transform duration-200" 
                :class="{ 'text-violet-600 dark:text-violet-400': isOpen }"
                fill="none" 
                viewBox="0 0 24 24" 
                stroke="currentColor"
            >
                <path 
                    stroke-linecap="round" 
                    stroke-linejoin="round" 
                    stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" 
                />
            </svg>
            <span 
                v-if="unreadCount > 0" 
                class="absolute top-1 right-1 h-4 w-4 rounded-full bg-red-500 text-[10px] text-white flex items-center justify-center animate-bounce shadow-sm"
                :class="{ 'animate-ping': unreadCount > 0 && !isOpen }"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <Transition 
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div 
                v-if="isOpen" 
                v-click-outside="() => emit('toggle')"
                class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-lg shadow-xl ring-1 ring-black/10 dark:ring-white/10 z-50 overflow-hidden"
            >
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span>Messages</span>
                        <span v-if="unreadCount > 0" class="text-xs bg-violet-100 dark:bg-violet-900/50 text-violet-800 dark:text-violet-200 px-2 py-0.5 rounded-full">
                            {{ unreadCount }} unread
                        </span>
                    </h3>
                    <button 
                        @click.stop="emit('toggle')" 
                        class="text-xs font-medium text-violet-600 dark:text-violet-400 hover:text-violet-800 dark:hover:text-violet-300 transition-colors"
                    >
                        Close
                    </button>
                </div>
                
                <div class="max-h-96 overflow-y-auto">
                    <TransitionGroup 
                        name="list" 
                        tag="div"
                        v-if="messages.length"
                    >
                        <div 
                            v-for="msg in messages" 
                            :key="msg.id"
                            @click.stop="markAsRead(msg.id)"
                            class="group relative px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700/50 transition-colors duration-150"
                            :class="{ 'bg-violet-50 dark:bg-violet-900/20': !msg.read }"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 relative">
                                    <img 
                                        :src="msg.sender.avatar" 
                                        :alt="msg.sender.name" 
                                        class="h-10 w-10 rounded-full object-cover ring-2 ring-white dark:ring-gray-700"
                                        :class="{ 'ring-violet-400': !msg.read }"
                                    />
                                    <span v-if="!msg.read" class="absolute -top-1 -right-1 h-3 w-3 rounded-full bg-violet-500 border-2 border-white dark:border-gray-800"></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-0.5">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" :class="{ 'font-semibold': !msg.read }">
                                            {{ msg.sender.name }}
                                        </p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap ml-2">{{ msg.time }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 truncate" :class="{ 'font-medium': !msg.read }">
                                        {{ msg.preview }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </TransitionGroup>
                    
                    <div v-else class="px-4 py-6 text-center">
                        <div class="mx-auto h-12 w-12 text-gray-400 mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No new messages</p>
                        <p class="text-xs text-gray-400 mt-1">Your messages will appear here</p>
                    </div>
                </div>
                
                <div v-if="messages.length > 0" class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 text-center">
                    <a href="#" class="text-xs font-medium text-violet-600 dark:text-violet-400 hover:underline">View all messages</a>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
    transition: all 0.3s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
</style>
