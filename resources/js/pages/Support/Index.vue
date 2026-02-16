<template>
    <Head title="Support" />
    <MainLayout>
        <div class="min-h-screen bg-gray-50 py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-4xl">
                    <div class="rounded-2xl bg-white p-8 shadow-sm">
                        <h1 class="text-3xl font-bold text-gray-900">
                            Contact Support
                        </h1>
                        <p class="mt-4 text-lg text-gray-600">
                            Our pet experts are here to help you with any
                            questions or concerns you may have.
                        </p>

                        <div class="mt-8">
                            <h2 class="text-xl font-semibold text-gray-900">
                                Ways to reach us
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
                                            Live Chat
                                        </h3>
                                        <p class="mt-1 text-gray-500">
                                            Chat with our support team in
                                            real-time
                                        </p>
                                        <button
                                            class="mt-3 inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-500"
                                        >
                                            Start chat
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
                                            Email Support
                                        </h3>
                                        <p class="mt-1 text-gray-500">
                                            Send us an email and we'll get back
                                            to you within 24 hours
                                        </p>
                                        <a
                                            href="mailto:support@petconnect.com"
                                            class="mt-3 inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-500"
                                        >
                                            Email us
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
import { Head } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { MessageSquare, Mail, ArrowRight } from 'lucide-vue-next';
</script>
