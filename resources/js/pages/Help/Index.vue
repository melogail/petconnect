<template>
    <Head :title="t('help.title')" />
    <MainLayout>
        <div class="min-h-screen bg-gray-50 py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-4xl">
                    <div class="rounded-2xl bg-white p-8 shadow-sm">
                        <h1 class="text-3xl font-bold text-gray-900">
                            {{ t('help.title') }}
                        </h1>
                        <p class="mt-4 text-lg text-gray-600">
                            {{ t('help.intro') }}
                        </p>

                        <div class="mt-8 space-y-8">
                            <!-- Quick Links -->
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900">
                                    {{ t('help.quick_links') }}
                                </h2>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <button
                                        class="flex items-center rounded-xl border border-gray-100 p-4 transition-all duration-200 hover:border-indigo-200"
                                    >
                                        <div
                                            class="mr-4 rounded-lg bg-indigo-50 p-2"
                                        >
                                            <PawPrint
                                                class="h-5 w-5 text-indigo-600"
                                            />
                                        </div>
                                        <div class="text-left">
                                            <h3
                                                class="font-medium text-gray-900"
                                            >
                                                {{ t('help.pet_profiles') }}
                                            </h3>
                                            <p
                                                class="mt-1 text-sm text-gray-500"
                                            >
                                                {{
                                                    t('help.pet_profiles_desc')
                                                }}
                                            </p>
                                        </div>
                                    </button>

                                    <button
                                        class="flex items-center rounded-xl border border-gray-100 p-4 transition-all duration-200 hover:border-indigo-200"
                                    >
                                        <div
                                            class="mr-4 rounded-lg bg-green-50 p-2"
                                        >
                                            <Settings
                                                class="h-5 w-5 text-green-600"
                                            />
                                        </div>
                                        <div class="text-left">
                                            <h3
                                                class="font-medium text-gray-900"
                                            >
                                                {{ t('help.account_settings') }}
                                            </h3>
                                            <p
                                                class="mt-1 text-sm text-gray-500"
                                            >
                                                {{
                                                    t(
                                                        'help.account_settings_desc',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- FAQs -->
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900">
                                    {{ t('help.faqs') }}
                                </h2>
                                <div class="mt-4 space-y-4">
                                    <div
                                        class="rounded-xl border border-gray-100 p-4"
                                    >
                                        <button
                                            class="flex w-full items-center justify-between text-left"
                                        >
                                            <h3
                                                class="font-medium text-gray-900"
                                            >
                                                {{ t('help.faq_add_pet') }}
                                            </h3>
                                            <ChevronDown
                                                class="h-5 w-5 text-gray-400"
                                            />
                                        </button>
                                    </div>

                                    <div
                                        class="rounded-xl border border-gray-100 p-4"
                                    >
                                        <button
                                            class="flex w-full items-center justify-between text-left"
                                        >
                                            <h3
                                                class="font-medium text-gray-900"
                                            >
                                                {{ t('help.faq_update_pet') }}
                                            </h3>
                                            <ChevronDown
                                                class="h-5 w-5 text-gray-400"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Support -->
                            <div class="border-t border-gray-100 pt-8">
                                <h2 class="text-xl font-semibold text-gray-900">
                                    {{ t('help.still_need_help') }}
                                </h2>
                                <p class="mt-2 text-gray-600">
                                    {{ t('help.still_need_help_desc') }}
                                </p>
                                <div class="mt-4">
                                    <Link
                                        :href="route('support')"
                                        class="inline-flex items-center rounded-lg border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    >
                                        <MessageSquare
                                            class="-ml-1 mr-2 h-5 w-5"
                                        />
                                        {{ t('help.contact_support') }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup lang="ts">
import { useTranslations } from '@/composables/useTranslations';
import MainLayout from '@/layouts/MainLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronDown,
    MessageSquare,
    PawPrint,
    Settings,
} from 'lucide-vue-next';
import { route } from 'ziggy-js';

const { t } = useTranslations();
</script>
