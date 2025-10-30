<template>
  <Head title="Help Center" />
  <MainLayout>
    <div class="min-h-screen bg-gray-50 py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
          <div class="bg-white rounded-2xl shadow-sm p-8">
            <h1 class="text-3xl font-bold text-gray-900">Help Center</h1>
            <p class="mt-4 text-lg text-gray-600">
              Find answers to frequently asked questions and learn how to make the most of PetConnect.
            </p>

            <div class="mt-8 space-y-8">
              <!-- Quick Links -->
              <div>
                <h2 class="text-xl font-semibold text-gray-900">Quick Links</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                  <button class="flex items-center p-4 rounded-xl border border-gray-100 hover:border-indigo-200 transition-all duration-200">
                    <div class="p-2 rounded-lg bg-indigo-50 mr-4">
                      <PawPrint class="h-5 w-5 text-indigo-600" />
                    </div>
                    <div class="text-left">
                      <h3 class="font-medium text-gray-900">Pet Profiles</h3>
                      <p class="mt-1 text-sm text-gray-500">Learn how to set up and manage pet profiles</p>
                    </div>
                  </button>

                  <button class="flex items-center p-4 rounded-xl border border-gray-100 hover:border-indigo-200 transition-all duration-200">
                    <div class="p-2 rounded-lg bg-green-50 mr-4">
                      <Settings class="h-5 w-5 text-green-600" />
                    </div>
                    <div class="text-left">
                      <h3 class="font-medium text-gray-900">Account Settings</h3>
                      <p class="mt-1 text-sm text-gray-500">Manage your account preferences</p>
                    </div>
                  </button>
                </div>
              </div>

              <!-- FAQs -->
              <div>
                <h2 class="text-xl font-semibold text-gray-900">Frequently Asked Questions</h2>
                <div class="mt-4 space-y-4">
                  <div class="p-4 rounded-xl border border-gray-100">
                    <button class="flex justify-between items-center w-full text-left">
                      <h3 class="font-medium text-gray-900">How do I add a new pet?</h3>
                      <ChevronDown class="h-5 w-5 text-gray-400" />
                    </button>
                  </div>

                  <div class="p-4 rounded-xl border border-gray-100">
                    <button class="flex justify-between items-center w-full text-left">
                      <h3 class="font-medium text-gray-900">How can I update my pet's information?</h3>
                      <ChevronDown class="h-5 w-5 text-gray-400" />
                    </button>
                  </div>
                </div>
              </div>

              <!-- Contact Support -->
              <div class="border-t border-gray-100 pt-8">
                <h2 class="text-xl font-semibold text-gray-900">Still need help?</h2>
                <p class="mt-2 text-gray-600">
                  Can't find what you're looking for? Our support team is here to help.
                </p>
                <div class="mt-4">
                  <Link
                    :href="route('support')"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    <MessageSquare class="-ml-1 mr-2 h-5 w-5" />
                    Contact Support
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
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import MainLayout from '@/layouts/MainLayout.vue';
import {
  PawPrint,
  Settings,
  MessageSquare,
  ChevronDown
} from 'lucide-vue-next';
</script>
