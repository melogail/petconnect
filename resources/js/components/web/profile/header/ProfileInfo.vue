<script setup lang="ts">
import { useAuthUser } from '@/composables/useAuthUser';
import {
    BadgeCheck,
    User,
    Calendar,
    MessageSquare,
    Edit2,
} from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const auth = useAuthUser();

defineProps({
    user: Object,
});

</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="space-y-1">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <h1
                        class="bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-3xl font-bold text-transparent dark:from-indigo-400 dark:to-violet-400 sm:text-4xl"
                    >
                        {{ user.data.name }}
                    </h1>
                    <span
                        v-if="!user.data.is_verified"
                        class="text-violet-500 dark:text-violet-400"
                        title="Verified account"
                    >
                        <BadgeCheck class="h-6 w-6 text-green-500" :size="24" />
                    </span>
                </div>
                <span
                    class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300"
                >
                    <User class="mr-1 h-3 w-3" />
                    Member
                </span>
            </div>
            <p
                class="flex items-center text-sm text-indigo-500 dark:text-indigo-400"
            >
                <Calendar class="mr-1.5 h-4 w-4" />
                Member since {{ user.data.created_at }}
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <Link
                v-if="auth && auth.id !== user.data.id"
                :href="route('conversations.store')"
                method="post"
                as="button"
                :data="{ other_user_id: user.data.id }"
                class="inline-flex cursor-pointer items-center rounded-xl border border-gray-200/50 bg-white/80 px-4 py-2.5 text-sm font-medium text-gray-700 backdrop-blur-sm transition-all hover:-translate-y-0.5 hover:bg-white hover:shadow-md dark:border-gray-600/50 dark:bg-gray-700/80 dark:text-gray-200 dark:hover:bg-gray-600"
            >
                <MessageSquare
                    class="mr-2 h-4 w-4 text-indigo-500 dark:text-indigo-400"
                />
                Messages
            </Link>
            <Link
                v-if="user.data.can.update"
                :href="route('profile.edit', user.data.id)"
                class="inline-flex cursor-pointer items-center rounded-xl border-0 bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 text-sm font-medium text-white transition-all hover:-translate-y-0.5 hover:from-indigo-700 hover:to-violet-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                <Edit2 class="mr-2 h-4 w-4 text-white/90" />
                Edit Profile
            </Link>
        </div>
    </div>
</template>
