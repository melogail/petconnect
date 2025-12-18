<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Home, MapPin, Camera, Stethoscope, Smile, Info, Check } from 'lucide-vue-next';
import { FileText } from 'lucide-vue-next';

interface Props {
    form: any;
    petTraits: Array<{ id: string; label: string }>;
}

const props = defineProps<Props>();
</script>

<template>
    <div id="step-8" class="step-container animate-fade-in">
        <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-green-100/50 dark:border-green-900/30 hover:border-green-300 dark:hover:border-green-700 shadow-lg">
            <!-- Animated Background Gradient -->
            <div class="absolute -z-10 inset-0 bg-gradient-to-br from-green-50/30 via-emerald-50/20 to-teal-50/10 dark:from-green-900/20 dark:via-emerald-900/10 dark:to-teal-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
            <!-- Decorative Corner -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-green-100/20 to-transparent dark:from-green-900/10 rounded-bl-full opacity-50"></div>
            <CardHeader class="relative z-10">
                <div class="flex items-center space-x-4">
                    <div class="relative p-3 rounded-2xl bg-gradient-to-br from-green-500 to-emerald-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                        <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                        <FileText class="h-6 w-6 relative z-10" />
                    </div>
                    <div>
                        <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Review Your Pet Listing</CardTitle>
                        <CardDescription class="text-gray-500 dark:text-gray-400">Please review all information before submitting</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Basic Information Review -->
                <div class="p-4 rounded-xl bg-gradient-to-r from-primary-50 to-purple-50 dark:from-primary-900/20 dark:to-purple-900/20 border border-primary-200 dark:border-primary-800">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-3 flex items-center">
                        <Home class="w-5 h-5 mr-2 text-primary-600 dark:text-primary-400" />
                        Basic Information
                    </h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500 dark:text-gray-400">Name:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.name || 'Not provided' }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Type:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.type || 'Not provided' }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Breed:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.breed || 'Not provided' }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Age:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.age || 'Not provided' }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Gender:</span> <span class="font-medium text-gray-800 dark:text-white capitalize">{{ form.gender || 'Not provided' }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Color:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.color || 'Not provided' }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Listing Type:</span> <span class="font-medium text-gray-800 dark:text-white capitalize">{{ form.listing_type.replace('_', ' ') || 'Adoption' }}</span></div>
                        <div v-if="form.listing_type === 'for_sale' && form.price"><span class="text-gray-500 dark:text-gray-400">Price:</span> <span class="font-medium text-gray-800 dark:text-white">${{ form.price }}</span></div>
                    </div>
                </div>

                <!-- Location Review -->
                <div class="p-4 rounded-xl bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 border border-blue-200 dark:border-blue-800">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-3 flex items-center">
                        <MapPin class="w-5 h-5 mr-2 text-blue-600 dark:text-blue-400" />
                        Location
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div v-if="form.location.address"><span class="text-gray-500 dark:text-gray-400">Address:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.location.address }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">City:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.location.city || 'Not provided' }}</span></div>
                        <div v-if="form.location.state"><span class="text-gray-500 dark:text-gray-400">State:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.location.state }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Country:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.location.country }}</span></div>
                        <div v-if="form.location.coordinates.lat && form.location.coordinates.lng"><span class="text-gray-500 dark:text-gray-400">Coordinates:</span> <span class="font-medium text-gray-800 dark:text-white font-mono text-xs">{{ form.location.coordinates.lat.toFixed(6) }}, {{ form.location.coordinates.lng.toFixed(6) }}</span></div>
                        <div v-if="form.location.detailedAddress" class="pt-2 border-t border-blue-200 dark:border-blue-800">
                            <span class="text-gray-500 dark:text-gray-400">Details:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.location.detailedAddress }}</span>
                        </div>
                    </div>
                </div>

                <!-- Photos Review -->
                <div class="p-4 rounded-xl bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border border-amber-200 dark:border-amber-800">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-3 flex items-center">
                        <Camera class="w-5 h-5 mr-2 text-amber-600 dark:text-amber-400" />
                        Photos
                    </h3>
                    <div class="grid grid-cols-4 gap-3">
                        <div v-if="form.featuredImagePreview" class="relative">
                            <img :src="form.featuredImagePreview" alt="Featured" class="w-full aspect-square object-cover rounded-lg border-2 border-amber-400" />
                            <span class="absolute top-1 left-1 bg-amber-500 text-white px-2 py-0.5 rounded text-xs font-semibold">Featured</span>
                        </div>
                        <img v-for="(preview, index) in form.imagePreviews" :key="index" :src="preview" :alt="`Photo ${index + 1}`" class="w-full aspect-square object-cover rounded-lg" />
                        <div v-if="!form.featuredImagePreview && form.imagePreviews.length === 0" class="col-span-4 text-center text-gray-500 dark:text-gray-400 py-4">
                            No photos uploaded
                        </div>
                    </div>
                </div>

                <!-- Health Review -->
                <div class="p-4 rounded-xl bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 border border-emerald-200 dark:border-emerald-800">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-3 flex items-center">
                        <Stethoscope class="w-5 h-5 mr-2 text-emerald-600 dark:text-emerald-400" />
                        Health Status
                    </h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500 dark:text-gray-400">Status:</span> <span class="font-medium text-gray-800 dark:text-white capitalize">{{ form.health.status }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Vaccinated:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.health.vaccinated ? 'Yes' : 'No' }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Spayed/Neutered:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.health.spayedNeutered ? 'Yes' : 'No' }}</span></div>
                        <div v-if="form.health.lastVetVisit"><span class="text-gray-500 dark:text-gray-400">Last Vet Visit:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.health.lastVetVisit }}</span></div>
                    </div>
                    <div v-if="form.health.specialNeeds" class="mt-3 pt-3 border-t border-emerald-200 dark:border-emerald-800">
                        <span class="text-gray-500 dark:text-gray-400 text-sm">Special Needs:</span>
                        <p class="font-medium text-gray-800 dark:text-white text-sm mt-1">{{ form.health.specialNeeds }}</p>
                    </div>
                </div>

                <!-- Personality Review -->
                <div class="p-4 rounded-xl bg-gradient-to-r from-purple-50 to-fuchsia-50 dark:from-purple-900/20 dark:to-fuchsia-900/20 border border-purple-200 dark:border-purple-800">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-3 flex items-center">
                        <Smile class="w-5 h-5 mr-2 text-purple-600 dark:text-purple-400" />
                        Personality
                    </h3>
                    <div v-if="form.description" class="mb-3">
                        <span class="text-gray-500 dark:text-gray-400 text-sm">Description:</span>
                        <p class="font-medium text-gray-800 dark:text-white text-sm mt-1">{{ form.description }}</p>
                    </div>
                    <div v-if="form.traits.length > 0">
                        <span class="text-gray-500 dark:text-gray-400 text-sm">Traits:</span>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span v-for="trait in form.traits" :key="trait" class="px-3 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-full text-xs font-medium">
                                {{ petTraits.find(t => t.id === trait)?.label }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Healthcare Review -->
                <div v-if="form.health.vaccinations?.length > 0 || form.health.medications?.length > 0 || form.health.allergies?.length > 0 || form.health.vetName" class="p-4 rounded-xl bg-gradient-to-r from-rose-50 to-pink-50 dark:from-rose-900/20 dark:to-pink-900/20 border border-rose-200 dark:border-rose-800">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-3 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        Healthcare Information
                    </h3>
                    
                    <!-- Vaccinations -->
                    <div v-if="form.health.vaccinations?.length > 0" class="mb-3">
                        <span class="text-gray-500 dark:text-gray-400 text-sm font-semibold">Vaccination Records:</span>
                        <div class="mt-2 space-y-2">
                            <div v-for="(vax, index) in form.health.vaccinations" :key="index" class="flex items-center gap-2 text-sm">
                                <span class="px-2 py-1 bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 rounded text-xs">{{ vax.date || 'No date' }}</span>
                                <span class="font-medium text-gray-800 dark:text-white">{{ vax.name || 'Unknown vaccine' }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medications -->
                    <div v-if="form.health.medications?.length > 0" class="mb-3 pt-3 border-t border-rose-200 dark:border-rose-800">
                        <span class="text-gray-500 dark:text-gray-400 text-sm font-semibold">Current Medications:</span>
                        <div class="mt-2 space-y-2">
                            <div v-for="(med, index) in form.health.medications" :key="index" class="text-sm">
                                <span class="font-medium text-gray-800 dark:text-white">{{ med.name || 'Unknown' }}</span>
                                <span v-if="med.usage" class="text-gray-500 dark:text-gray-400"> - {{ med.usage }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Allergies -->
                    <div v-if="form.health.allergies?.length > 0" class="mb-3 pt-3 border-t border-rose-200 dark:border-rose-800">
                        <span class="text-gray-500 dark:text-gray-400 text-sm font-semibold">Allergies:</span>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span v-for="(allergy, index) in form.health.allergies" :key="index" class="px-3 py-1 bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 rounded-full text-xs font-medium">
                                {{ allergy || 'Unknown' }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Veterinarian -->
                    <div v-if="form.health.vetName || form.health.vetPhone" class="pt-3 border-t border-rose-200 dark:border-rose-800">
                        <span class="text-gray-500 dark:text-gray-400 text-sm font-semibold">Veterinarian:</span>
                        <div class="mt-2 text-sm">
                            <div v-if="form.health.vetName"><span class="font-medium text-gray-800 dark:text-white">{{ form.health.vetName }}</span></div>
                            <div v-if="form.health.vetPhone" class="text-gray-600 dark:text-gray-300">{{ form.health.vetPhone }}</div>
                        </div>
                    </div>
                </div>

                <!-- Additional Info Review -->
                <div v-if="form.additionalInfo.some(info => info.key && info.value)" class="p-4 rounded-xl bg-gradient-to-r from-indigo-50 to-blue-50 dark:from-indigo-900/20 dark:to-blue-900/20 border border-indigo-200 dark:border-indigo-800">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-3 flex items-center">
                        <Info class="w-5 h-5 mr-2 text-indigo-600 dark:text-indigo-400" />
                        Additional Information
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div v-for="(info, index) in form.additionalInfo" :key="index">
                            <div v-if="info.key && info.value">
                                <span class="text-gray-500 dark:text-gray-400">{{ info.key }}:</span>
                                <span class="font-medium text-gray-800 dark:text-white ml-2">{{ info.value }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirmation Message -->
                <div class="p-4 rounded-xl bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-2 border-green-300 dark:border-green-700">
                    <div class="flex items-start space-x-3">
                        <Check class="w-6 h-6 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-white mb-1">Ready to Submit</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Please review all the information above. Once you submit, your pet listing will be created and visible to others.</p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
/* Fix text blurriness on hover/scale transforms */
.group:hover {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    transform-style: preserve-3d;
    -webkit-transform-style: preserve-3d;
}

/* Prevent text blur on transform elements */
[class*="transition"],
[class*="transform"],
[class*="scale"] {
    will-change: transform;
    transform: translateZ(0);
    -webkit-transform: translateZ(0);
}
</style>
