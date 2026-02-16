<script setup lang="ts">
import ProfileAvatar from './header/ProfileAvatar.vue';
import ProfileInfo from './header/ProfileInfo.vue';
import ProfileDetails from './header/ProfileDetails.vue';
import ProfileBio from './header/ProfileBio.vue';

defineProps({
    user: Object,
});
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-gray-100 bg-gradient-to-br from-indigo-50 to-violet-50 shadow-lg dark:border-gray-700 dark:from-gray-800 dark:to-gray-900"
    >
        <div class="relative px-6 py-8 sm:p-10">
            <div class="flex flex-col items-start gap-8 lg:flex-row">
                <!-- Profile Picture with Decorative Border -->
                <ProfileAvatar :user="user" />

                <!-- User Info -->
                <div class="min-w-0 flex-1 space-y-4">
                    <ProfileInfo :user="user" />

                    <!-- User Details Grid -->
                    <ProfileDetails :user="user" />

                    <!-- Bio -->
                    <ProfileBio :user="user" />
                </div>
            </div>
        </div>
    </div>
</template>
