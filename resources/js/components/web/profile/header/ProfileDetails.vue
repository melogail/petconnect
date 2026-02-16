<script setup lang="ts">
import { Mail, Phone, MapPin } from 'lucide-vue-next';

defineProps({
    user: Object,
});
</script>

<template>
    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div
            class="rounded-xl border border-gray-100/50 bg-white/50 p-3 backdrop-blur-sm transition-colors hover:bg-white dark:border-gray-700/50 dark:bg-gray-800/50 dark:hover:bg-gray-700/70"
        >
            <div class="flex items-center">
                <div
                    class="mr-3 rounded-lg bg-indigo-50 p-2 dark:bg-indigo-900/30"
                >
                    <Mail
                        class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
                    />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Email
                    </p>
                    <p
                        class="truncate text-sm font-medium text-gray-900 dark:text-white"
                    >
                        {{ user.data.email }}
                    </p>
                </div>
            </div>
        </div>
        <div
            v-if="user.data.phone || user.data.can.update"
            class="rounded-xl border border-gray-100/50 bg-white/50 p-3 backdrop-blur-sm transition-colors hover:bg-white dark:border-gray-700/50 dark:bg-gray-800/50 dark:hover:bg-gray-700/70"
        >
            <div class="flex items-center">
                <div
                    class="mr-3 rounded-lg bg-violet-50 p-2 dark:bg-violet-900/30"
                >
                    <Phone
                        class="h-5 w-5 text-violet-600 dark:text-violet-400"
                    />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Phone
                    </p>
                    <p
                        class="text-sm font-medium text-gray-900 dark:text-white"
                    >
                        {{
                            user.data.phone ??
                            '+ Make it easier for people to contact you.'
                        }}
                    </p>
                </div>
            </div>
        </div>
        <div
            v-if="user.data.location || user.data.can.update"
            class="rounded-xl border border-gray-100/50 bg-white/50 p-3 backdrop-blur-sm transition-colors hover:bg-white dark:border-gray-700/50 dark:bg-gray-800/50 dark:hover:bg-gray-700/70"
        >
            <div class="flex items-center">
                <div
                    class="mr-3 rounded-lg bg-emerald-50 p-2 dark:bg-emerald-900/30"
                >
                    <MapPin
                        class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                    />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Location
                    </p>
                    <p class="text-sm font-medium dark:text-white">
                        {{
                            user.data.location ??
                            '+ Add your location to find people nearby.'
                        }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
