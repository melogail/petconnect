<script setup lang="ts">
import { ref } from 'vue';
import { TransitionGroup } from 'vue';

defineProps<{
    notifications: Array<{ id: number; text: string; time: string; read: boolean; type: string }>;
    unreadCount: number;
    isOpen: boolean;
}>();

const emit = defineEmits(['toggle', 'markAsRead', 'markAllAsRead']);

const markAsRead = (id: number) => emit('markAsRead', id);
const markAllAsRead = () => emit('markAllAsRead');

const getNotificationIcon = (type: string) => {
    const icons = {
        message: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
        alert: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
    };
    return icons[type as keyof typeof icons] || icons.info;
};
</script>

<template>
    <div class="relative">
        <button 
            @click.stop="emit('toggle')" 
            class="relative p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
            :aria-expanded="isOpen"
            :aria-label="`Notifications ${unreadCount > 0 ? `(${unreadCount} unread)` : ''}`"
        >
            <span class="sr-only">Notifications</span>
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
                    d="M15 17h5l-1.405-1.405C18.21 14.79 18 13.918 18 13V8a6 6 0 00-12 0v5c0 .918-.21 1.79-.595 2.595L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" 
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
                        <span>Notifications</span>
                        <span v-if="unreadCount > 0" class="text-xs bg-violet-100 dark:bg-violet-900/50 text-violet-800 dark:text-violet-200 px-2 py-0.5 rounded-full">
                            {{ unreadCount }} unread
                        </span>
                    </h3>
                    <button 
                        @click.stop="markAllAsRead" 
                        class="text-xs font-medium text-violet-600 dark:text-violet-400 hover:text-violet-800 dark:hover:text-violet-300 transition-colors"
                        :disabled="unreadCount === 0"
                        :class="{ 'opacity-50 cursor-not-allowed': unreadCount === 0 }"
                    >
                        Mark all as read
                    </button>
                </div>
                
                <div class="max-h-96 overflow-y-auto">
                    <TransitionGroup 
                        name="list" 
                        tag="div"
                        v-if="notifications.length"
                    >
                        <div 
                            v-for="n in notifications" 
                            :key="n.id"
                            @click.stop="markAsRead(n.id)"
                            class="group relative px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700/50 transition-colors duration-150"
                            :class="{ 'bg-violet-50 dark:bg-violet-900/20': !n.read }"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 mt-0.5">
                                    <div class="h-8 w-8 rounded-full bg-violet-100 dark:bg-violet-900/50 flex items-center justify-center">
                                        <svg 
                                            xmlns="http://www.w3.org/2000/svg" 
                                            class="h-4 w-4 text-violet-600 dark:text-violet-400" 
                                            fill="none" 
                                            viewBox="0 0 24 24" 
                                            stroke="currentColor"
                                        >
                                            <path 
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                :d="getNotificationIcon(n.type)" 
                                            />
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-800 dark:text-gray-100 font-medium leading-tight mb-0.5" :class="{ 'font-semibold': !n.read }">
                                        {{ n.text }}
                                    </p>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ n.time }}</span>
                                        <span v-if="!n.read" class="h-2 w-2 rounded-full bg-violet-500"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </TransitionGroup>
                    
                    <div v-else class="px-4 py-6 text-center">
                        <div class="mx-auto h-12 w-12 text-gray-400 mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405C18.21 14.79 18 13.918 18 13V8a6 6 0 00-12 0v5c0 .918-.21 1.79-.595 2.595L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No new notifications</p>
                        <p class="text-xs text-gray-400 mt-1">We'll let you know when there's something new.</p>
                    </div>
                </div>
                
                <div v-if="notifications.length > 0" class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 text-center">
                    <a href="#" class="text-xs font-medium text-violet-600 dark:text-violet-400 hover:underline">View all notifications</a>
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
