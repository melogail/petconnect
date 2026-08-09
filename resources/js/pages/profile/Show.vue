<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import ProfileHeader from '@/components/web/profile/ProfileHeader.vue';
import ProfilePetsTable from '@/components/web/profile/ProfilePetsTable.vue';
import ProfileReviewsTab from '@/components/web/profile/ProfileReviewsTab.vue';
import {
    User,
    Mail,
    Phone,
    MapPin,
    Calendar,
    Edit2,
    Trash2,
    Eye,
    MessageSquare,
    FileText,
    Star,
    MessageCircle,
    Info,
    Plus,
    Settings,
    Bell,
    EyeOff,
    MoreVertical,
    BadgeCheck,
    ThumbsUp,
    ThumbsDown,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { route } from 'ziggy-js';
import { useTranslations } from '@/composables/useTranslations';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';

const { t } = useTranslations();

const props = defineProps({
    user: Object,
    reportReasons: {
        type: Array,
        default: () => [],
    },
});

// Tabs state
const activeTab = ref('Pets');
const tabs = computed(() => [
    {
        name: 'Pets',
        label: t('profile.pets'),
        icon: 'FileText',
        count: props.user?.data?.pets?.length ?? 0,
    },
    {
        name: 'Reviews',
        label: t('profile.reviews'),
        icon: 'Star',
        count: props.user?.data?.reviews?.length ?? 0,
    },
]);
</script>

<template>
    <Head :title="t('profile.title')" />
    <MainLayout class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Enhanced Profile Header -->
            <ProfileHeader :user="user" />

            <!-- Tabs Navigation -->
            <div class="mt-8">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex gap-8" aria-label="Tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.name"
                            @click="activeTab = tab.name"
                            :class="[
                                activeTab === tab.name
                                    ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                                'flex items-center whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium',
                            ]"
                        >
                            <component :is="tab.icon" class="me-2 h-5 w-5" />
                            {{ tab.label }}
                            <span
                                v-if="tab.count !== undefined"
                                class="ms-2 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium dark:bg-gray-700"
                            >
                                {{ tab.count }}
                            </span>
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="mt-6">
                <!-- Pets Tab -->
                <div v-show="activeTab === 'Pets'" class="space-y-6">
                    <ProfilePetsTable
                        :pets="user.data.pets"
                        :userCanCreate="user.data.can.create"
                    />
                </div>

                <!-- Reviews Tab -->
                <div v-show="activeTab === 'Reviews'">
                    <ProfileReviewsTab
                        :reviews="user.data.reviews || []"
                        :report-reasons="reportReasons"
                        :profile-owner-id="user.data.id"
                    />
                </div>
            </div>
        </div>
    </MainLayout>
</template>
