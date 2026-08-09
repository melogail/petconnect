<template>
    <Head :title="t('support.title')" />
    <MainLayout>
        <div class="min-h-screen bg-gray-50 py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-4xl">
                    <div class="rounded-2xl bg-white p-8 shadow-sm">
                        <h1 class="text-3xl font-bold text-gray-900">
                            {{ t('support.contact_support') }}
                        </h1>
                        <p class="mt-4 text-lg text-gray-600">
                            {{ t('support.intro') }}
                        </p>

                        <div class="mt-8">
                            <h2 class="text-xl font-semibold text-gray-900">
                                {{ t('support.ways_to_reach_us') }}
                            </h2>
                            <div class="mt-6 grid gap-6">
                                <div
                                    class="flex items-start gap-4 rounded-xl border border-gray-100 p-4 transition-all duration-200 hover:border-indigo-200"
                                >
                                    <div class="rounded-lg bg-indigo-50 p-3">
                                        <MessageSquare
                                            class="h-6 w-6 text-indigo-600"
                                        />
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">
                                            {{ t('support.live_chat') }}
                                        </h3>
                                        <p class="mt-1 text-gray-500">
                                            {{ t('support.live_chat_desc') }}
                                        </p>
                                        <button
                                            class="mt-3 inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-500"
                                        >
                                            {{ t('support.start_chat') }}
                                            <ArrowRight class="ml-2 h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <div
                                    class="flex items-start gap-4 rounded-xl border border-gray-100 p-4 transition-all duration-200 hover:border-indigo-200"
                                >
                                    <div class="rounded-lg bg-pink-50 p-3">
                                        <Mail class="h-6 w-6 text-pink-600" />
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">
                                            {{ t('support.email_support') }}
                                        </h3>
                                        <p class="mt-1 text-gray-500">
                                            {{
                                                t('support.email_support_desc')
                                            }}
                                        </p>
                                        <a
                                            href="mailto:support@petconnect.com"
                                            class="mt-3 inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-500"
                                        >
                                            {{ t('support.email_us') }}
                                            <ArrowRight class="ml-2 h-4 w-4" />
                                        </a>
                                    </div>
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
import { Head } from '@inertiajs/vue3';
import { ArrowRight, Mail, MessageSquare } from 'lucide-vue-next';

const { t } = useTranslations();
</script>
