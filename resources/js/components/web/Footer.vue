<script setup>
// No props or reactive data needed for this static footer
</script>

<template>
    <footer class="bg-gray-50 border-t border-gray-200 dark:bg-gray-900 dark:border-gray-800">
        <!-- Main Footer Content -->
        <div class="max-w-7xl mx-auto px-6 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Brand Section -->
                <div class="lg:col-span-1">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-violet-500 rounded-2xl flex items-center justify-center">
                            <svg class="w-7 h-7 text-white" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                                 viewBox="0 0 24 24">
                                <path
                                    d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-gray-800 dark:text-white">PetConnect</span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">
                        Connecting pet lovers and breeders worldwide. Find your perfect companion or help a pet find
                        their forever home.
                    </p>
                    <!-- Social Media Icons -->
                    <div class="flex items-center gap-3">
                        <a href="#"
                           class="w-9 h-9 flex items-center justify-center rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-violet-50 dark:hover:bg-gray-700 hover:text-violet-600 hover:border-violet-200 dark:hover:border-violet-500 transition-colors">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                                 viewBox="0 0 24 24">
                                <path
                                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                            </svg>
                        </a>
                        <a href="#"
                           class="w-9 h-9 flex items-center justify-center rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-violet-50 dark:hover:bg-gray-700 hover:text-violet-600 hover:border-violet-200 dark:hover:border-violet-500 transition-colors">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                                 viewBox="0 0 24 24">
                                <path
                                    d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z" />
                            </svg>
                        </a>
                        <a href="#"
                           class="w-9 h-9 flex items-center justify-center rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-violet-50 dark:hover:bg-gray-700 hover:text-violet-600 hover:border-violet-200 dark:hover:border-violet-500 transition-colors">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                                 viewBox="0 0 24 24">
                                <path
                                    d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                            </svg>
                        </a>
                        <a href="#"
                           class="w-9 h-9 flex items-center justify-center rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-violet-50 dark:hover:bg-gray-700 hover:text-violet-600 hover:border-violet-200 dark:hover:border-violet-500 transition-colors">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                                 viewBox="0 0 24 24">
                                <path
                                    d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-4">Quick Links</h3>
                    <ul class="space-y-2.5">
                        <li><a href="#" class="text-sm dark:text-gray-400 text-gray-600 hover:text-violet-600 transition-colors">Browse
                            Pets</a></li>
                        <li><a href="#" class="text-sm dark:text-gray-400 text-gray-600 hover:text-violet-600 transition-colors">Post a
                            Pet</a></li>
                        <li><a href="#"
                               class="text-sm dark:text-gray-400 text-gray-600 hover:text-violet-600 transition-colors">Dashboard</a></li>
                        <li><a href="#" class="text-sm dark:text-gray-400 text-gray-600 hover:text-violet-600 transition-colors">Adoption
                            Guide</a></li>
                        <li><a href="#" class="text-sm dark:text-gray-400 text-gray-600 hover:text-violet-600 transition-colors">Breeding
                            Resources</a></li>
                    </ul>
                </div>

                <!-- Pet Categories -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-4">Pet Categories</h3>
                    <ul class="space-y-2.5 ">
                        <li><a href="#" class="text-sm text-gray-600 dark:text-gray-400 hover:text-violet-600 transition-colors">Dogs</a>
                        </li>
                        <li><a href="#" class="text-sm text-gray-600 dark:text-gray-400 hover:text-violet-600 transition-colors">Cats</a>
                        </li>
                        <li><a href="#" class="text-sm text-gray-600 dark:text-gray-400 hover:text-violet-600 transition-colors">Birds</a>
                        </li>
                        <li><a href="#" class="text-sm text-gray-600 dark:text-gray-400 hover:text-violet-600 transition-colors">Horses</a>
                        </li>
                        <li><a href="#" class="text-sm text-gray-600 dark:text-gray-400 hover:text-violet-600 transition-colors">Other
                            Pets</a></li>
                    </ul>
                </div>

                <!-- Contact Us -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-4">Contact Us</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="4" width="20" height="16" rx="2" stroke-linecap="round"
                                      stroke-linejoin="round" />
                                <path d="m2 7 10 6 10-6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <a href="mailto:support@petconnect.com"
                               class="text-sm text-gray-600 dark:text-gray-400 hover:text-violet-600 transition-colors">support@petconnect.com</a>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path
                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span class="text-sm text-gray-600 dark:text-gray-400">+1 (234) 567-890</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke-linecap="round"
                                      stroke-linejoin="round" />
                                <circle cx="12" cy="10" r="3" />
                            </svg>
                            <span class="text-sm text-gray-600 dark:text-gray-400">123 Pet Street, Animal City, AC 12345</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Bottom Footer -->
        <div class="border-t border-gray-200 dark:border-gray-800">
            <div class="max-w-7xl mx-auto px-6 py-4">
                <div class="flex flex-col md:flex-row items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">© 2025 PetConnect. All rights reserved.</p>
                    <div class="flex items-center gap-6">
                        <a href="#" class="text-sm text-gray-500 hover:text-violet-600 transition-colors">Privacy
                            Policy</a>
                        <a href="#" class="text-sm text-gray-500 hover:text-violet-600 transition-colors">Terms of
                            Service</a>
                        <a href="#" class="text-sm text-gray-500 hover:text-violet-600 transition-colors">Cookie
                            Policy</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</template>

<style scoped>
/* Additional styles if needed */
</style>
