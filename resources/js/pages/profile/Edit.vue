<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import { ref } from 'vue';
import { MapPin, Shield, User } from 'lucide-vue-next';
import { route } from 'ziggy-js';

// Section Components
import EditHeader from '@/components/web/profile/edit/EditHeader.vue';
import BasicInfoSection from '@/components/web/profile/edit/BasicInfoSection.vue';
import LocationSection from '@/components/web/profile/edit/LocationSection.vue';
import SecuritySection from '@/components/web/profile/edit/SecuritySection.vue';

const props = defineProps({
    user: Object,
});

const form = useForm({
    name: props.user.data?.name || '',
    email: props.user.data?.email || '',
    phone: props.user.data?.phone || '',
    bio: props.user.data?.bio || '',
    address: props.user.data?.address || '',
    city: props.user.data?.city || '',
    state: props.user.data?.state || '',
    country: props.user.data?.country || '',
    lat: props.user.data?.lat || null,
    lng: props.user.data?.lng || null,
    timezone: props.user.data?.timezone || '',
    locale: props.user.data?.locale || 'en',
    is_active: props.user.data?.is_active ?? true,
    current_password: '',
    new_password: '',
    confirm_password: '',
    two_factor_enabled: props.user.data?.two_factor_enabled || false,
    profile_image: null as File | null,
});

// Navigation State
const activeSection = ref('basic');
const sections = [
    {
        id: 'basic',
        label: 'Basic Information',
        icon: User,
        description: 'Manage your profile details',
    },
    {
        id: 'location',
        label: 'Location',
        icon: MapPin,
        description: 'Update where you live',
    },
    {
        id: 'security',
        label: 'Security',
        icon: Shield,
        description: 'Secure your account',
    },
];

const submit = () => {
    form.post(route('profile.update', props.user.data?.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset(
                'current_password',
                'new_password',
                'confirm_password',
                'profile_image',
            );
        },
    });
};
</script>

<template>
    <Head title="Edit Profile" />

    <MainLayout class="min-h-screen bg-gray-50/50 dark:bg-gray-900">
        <!-- Reusable Header Component -->
        <EditHeader />

        <div
            class="relative z-10 mx-auto -mt-24 max-w-7xl px-4 pb-12 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl bg-white shadow-xl ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
            >
                <form
                    @submit.prevent="submit"
                    class="flex flex-col lg:flex-row"
                >
                    <!-- Sidebar Navigation -->
                    <aside
                        class="w-full border-b border-gray-100 px-4 py-6 dark:border-gray-700 lg:w-72 lg:flex-shrink-0 lg:border-b-0 lg:border-r lg:px-6 lg:py-8"
                    >
                        <nav class="space-y-1">
                            <button
                                v-for="section in sections"
                                :key="section.id"
                                type="button"
                                @click="activeSection = section.id"
                                :class="[
                                    activeSection === section.id
                                        ? 'bg-violet-50 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200',
                                    'group flex w-full items-center rounded-xl px-3 py-3 text-sm font-medium transition-all duration-200 ease-in-out',
                                ]"
                            >
                                <component
                                    :is="section.icon"
                                    :class="[
                                        activeSection === section.id
                                            ? 'text-violet-600 dark:text-violet-400'
                                            : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-500 dark:group-hover:text-gray-300',
                                        'mr-3 h-5 w-5 flex-shrink-0',
                                    ]"
                                    aria-hidden="true"
                                />
                                <div class="text-left">
                                    <span class="block">{{
                                        section.label
                                    }}</span>
                                    <span
                                        v-if="activeSection === section.id"
                                        class="mt-0.5 block text-xs font-normal opacity-80"
                                    >
                                        {{ section.description }}
                                    </span>
                                </div>
                                <div
                                    v-if="activeSection === section.id"
                                    class="ml-auto h-1 w-1 rounded-full bg-violet-500"
                                />
                            </button>
                        </nav>
                    </aside>

                    <!-- Content Area -->
                    <div class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                        <BasicInfoSection
                            v-show="activeSection === 'basic'"
                            :form="form"
                            :profile-image="user.data?.profile_image"
                        />
                        <LocationSection
                            v-show="activeSection === 'location'"
                            :form="form"
                        />
                        <SecuritySection
                            v-show="activeSection === 'security'"
                            :form="form"
                        />

                        <!-- General Actions -->
                        <div
                            class="mt-8 flex items-center justify-end gap-x-4 border-t border-gray-100 pt-6 dark:border-gray-700"
                        >
                            <p
                                v-if="form.recentlySuccessful"
                                class="text-sm text-green-600 dark:text-green-400"
                            >
                                Saved successfully.
                            </p>
                            <Link
                                :href="route('profile.show', user.data.id)"
                                class="cursor-pointer rounded-xl bg-gray-200 px-4 py-2 transition-all duration-200 ease-in-out hover:bg-gray-300 hover:text-gray-900 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600 dark:hover:text-gray-200"
                            >
                                Cancel
                            </Link>
                            <Button
                                @click="submit"
                                type="submit"
                                :disabled="form.processing"
                                class="cursor-pointer bg-violet-600 text-white shadow-md transition-all hover:bg-violet-700 hover:shadow-lg dark:bg-violet-500 dark:hover:bg-violet-600"
                            >
                                Save Changes
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </MainLayout>
</template>
