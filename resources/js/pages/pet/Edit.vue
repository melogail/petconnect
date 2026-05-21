<script setup lang="ts">
import { Button } from '@/components/ui/button';
import MainLayout from '@/layouts/MainLayout.vue';
import { useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Home,
    MapPin,
    Camera,
    Stethoscope,
    Smile,
    Info,
    Heart,
    FileText,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';
import imageCompression from 'browser-image-compression';

// Import step components
import AdditionalInfoStep from '@/components/web/pet/form/AdditionalInfoStep.vue';
import BasicInfoStep from '@/components/web/pet/form/BasicInfoStep.vue';
import HealthcareStep from '@/components/web/pet/form/HealthcareStep.vue';
import HealthStep from '@/components/web/pet/form/HealthStep.vue';
import LocationStep from '@/components/web/pet/form/LocationStep.vue';
import PersonalityStep from '@/components/web/pet/form/PersonalityStep.vue';
import PhotosStep from '@/components/web/pet/form/PhotosStep.vue';
import ReviewStep from '@/components/web/pet/form/ReviewStep.vue';
import StepperProgress from '@/components/web/pet/form/StepperProgress.vue';
import { route } from 'ziggy-js';

interface Props {
    pet: { data: any } | any; // To handle both wrapped Resource and direct object
    petCategories: { data: any[] };
    listingTypes: Array<{ value: number; label: string }>;
}

const props = defineProps<Props>();

// Handle potential resource wrapper
const petData = computed(() => {
    return props.pet.data ? props.pet.data : props.pet;
});

/** HTML date inputs require YYYY-MM-DD; API may still send ISO datetimes in edge cases. */
function dateToInputString(value: unknown): string {
    if (value == null || value === '') {
        return '';
    }
    const match = String(value).match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : '';
}

// Pet categories and breeds
const categories = computed(() =>
    (props.petCategories.data || []).map((cat: any) => ({
        id: cat.id,
        name: cat.name,
    })),
);

// Type definition for breeds map
interface BreedsMap {
    [key: string]: Array<{ id: string; name: string }>;
}

const breeds = computed(() => {
    const result: BreedsMap = {};
    (props.petCategories.data || []).forEach((cat: any) => {
        result[cat.id] = cat.breeds.map((breed: any) => ({
            id: breed.id,
            name: breed.name,
        }));
    });
    return result;
});

// Pet traits
const petTraits = [
    { id: 'Friendly', label: 'Friendly' },
    { id: 'Playful', label: 'Playful' },
    { id: 'Calm', label: 'Calm' },
    { id: 'Energetic', label: 'Energetic' },
    { id: 'Shy', label: 'Shy' },
    { id: 'Loyal', label: 'Loyal' },
    { id: 'Smart', label: 'Smart' },
    { id: 'Gentle', label: 'Gentle' },
    { id: 'Affectionate', label: 'Affectionate' },
    { id: 'Independent', label: 'Independent' },
    { id: 'Intelligent', label: 'Intelligent' },
];

// Default to single page view, avoiding steps for edit
// Form state populated with existing pet data
const form = useForm({
    _method: 'PUT',
    name: petData.value.name || '',
    type: petData.value.category?.id || '',
    breed: petData.value.breed?.id || '',
    age: petData.value.age || '',
    color: petData.value.color || '',
    weight: petData.value.weight || '',
    gender: petData.value.gender || '',
    description: petData.value.description || '',
    listing_type: petData.value.listing_type || (props.listingTypes[0]?.value || 1),
    price: petData.value.price || '',
    status: petData.value.status || 'available',
    
    // Flatten location
    location: {
        address: petData.value.address || '',
        detailedAddress: petData.value.detailed_address || '',
        city: petData.value.city || '',
        state: petData.value.state || '',
        postalCode: petData.value.postal_code || '',
        country: petData.value.country || '',
        coordinates: {
            lat: petData.value.latitude || 0,
            lng: petData.value.longitude || 0,
        },
    },
    
    // Images
    images: [] as File[],
    imagePreviews: petData.value.images?.map((img: any) => img.url) || [] as string[],
    existingImages: petData.value.images || [],
    deletedMediaIds: [] as number[],
    featuredImage: null as File | null,
    featuredImagePreview: petData.value.feature_image || '' as string,
    
    // Health (Combine basic and detailed health)
    health: {
        status: petData.value.health_status || 'healthy',
        vaccinated: petData.value.vaccinated ?? true,
        spayedNeutered: petData.value.spayed_neutered ?? true,
        specialNeeds: petData.value.special_needs || '',
        lastVetVisit: dateToInputString(petData.value.last_vet_visit),
        vaccinations: (typeof petData.value.vaccinations === 'string' ? JSON.parse(petData.value.vaccinations) : petData.value.vaccinations) || [],
        medications: (typeof petData.value.medications === 'string' ? JSON.parse(petData.value.medications) : petData.value.medications) || [],
        allergies: (typeof petData.value.allergies === 'string' ? JSON.parse(petData.value.allergies) : petData.value.allergies) || [],
        vetName: petData.value.vet_name || '',
        vetPhone: petData.value.vet_phone || '',
    },
    
    // Traits and Additional (normalize casing to match PersonalityStep trait ids)
    traits: ((typeof petData.value.traits === 'string' ? JSON.parse(petData.value.traits) : petData.value.traits) || []).map(
        (t: string) => t.charAt(0).toUpperCase() + t.slice(1).toLowerCase(),
    ),
    additionalInfo: (typeof petData.value.additional_info === 'string' ? JSON.parse(petData.value.additional_info) : petData.value.additional_info) || [],
});

// Since Edit uses subsections instead of stepper progress
const activeSection = ref(1);

const sections = [
    { id: 1, name: 'Basic & Personality', icon: Home, refId: 'section-basic' },
    { id: 2, name: 'Location', icon: MapPin, refId: 'section-location' },
    { id: 3, name: 'Health', icon: Stethoscope, refId: 'section-health' },
    { id: 4, name: 'Media', icon: Camera, refId: 'section-media' }
];

const scrollToSection = (id: number) => {
    activeSection.value = id;
    const sectionToScroll = sections.find(s => s.id === id);
    if (sectionToScroll) {
        const el = document.getElementById(sectionToScroll.refId);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
};

// Map state
const mapCenter = ref({ lat: parseFloat((form.location.coordinates.lat || 39.8283).toString()), lng: parseFloat((form.location.coordinates.lng || -98.5795).toString()) });
const mapMarker = ref({ lat: parseFloat((form.location.coordinates.lat || 39.8283).toString()), lng: parseFloat((form.location.coordinates.lng || -98.5795).toString()) });
const isLoadingLocation = ref(false);


// Image compression configuration
const MAX_TOTAL_SIZE_MB = 0.5;
const MAX_TOTAL_SIZE_BYTES = MAX_TOTAL_SIZE_MB * 1024 * 1024;

const compressImage = async (file: File, targetSizeKB: number): Promise<File> => {
    const options = {
        maxSizeMB: targetSizeKB / 1024,
        maxWidthOrHeight: 1920,
        useWebWorker: true,
        fileType: file.type as any,
    };

    try {
        return await imageCompression(file, options);
    } catch (error) {
        console.error('Image compression failed:', error);
        return file;
    }
};

const calculateTargetSize = (totalImages: number): number => {
    const availableSize = MAX_TOTAL_SIZE_BYTES * 0.9;
    return Math.floor(availableSize / totalImages / 1024);
};

// Methods
const addInfoField = () => {
    if (!form.additionalInfo) {
        form.additionalInfo = [];
    }
    form.additionalInfo.push({ key: '', value: '' });
};

const removeInfoField = (index: number) => {
    form.additionalInfo.splice(index, 1);
};

const handleFileUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);

    if (files.length + form.images.length + form.existingImages.length > 3) {
        alert('You can only have up to 3 gallery images total');
        return;
    }

    const totalImages = files.length + form.images.length + (form.featuredImage ? 1 : 0);
    const targetSizeKB = calculateTargetSize(totalImages > 0 ? totalImages : 1);

    for (const file of files) {
        const compressedFile = await compressImage(file, targetSizeKB);
        form.images.push(compressedFile);

        const reader = new FileReader();
        reader.onload = (e) => {
            form.imagePreviews.push(e.target?.result as string);
        };
        reader.readAsDataURL(compressedFile);
    }

    target.value = '';
};

const handleFeaturedImageUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        const totalImages = form.images.length + 1;
        const targetSizeKB = calculateTargetSize(totalImages);

        const compressedFile = await compressImage(file, targetSizeKB);
        form.featuredImage = compressedFile;

        const reader = new FileReader();
        reader.onload = (e) => {
            form.featuredImagePreview = e.target?.result as string;
        };
        reader.readAsDataURL(compressedFile);
    }

    target.value = '';
};

const removeFeaturedImage = () => {
    form.featuredImage = null;
    form.featuredImagePreview = '';
};

const removeImage = (index: number) => {
    // Determine if it's an existing image or newly uploaded
    if (index < form.existingImages.length) {
        // It's an existing image
        const img = form.existingImages[index];
        form.deletedMediaIds.push(img.id);
        form.existingImages.splice(index, 1);
    } else {
        // It's a newly added image (adjust index)
        const adjustedIndex = index - form.existingImages.length;
        form.images.splice(adjustedIndex, 1);
    }
    form.imagePreviews.splice(index, 1);
};


// Strict validation for form submission
const validateForm = (): boolean => {
    if (!form.name || !form.type || !form.breed || !form.age || !form.gender) {
        alert('Please fill in all basic information fields in the Basic section.');
        scrollToSection(1);
        return false;
    }
    if (!form.location.city || !form.location.country) {
        alert('Please provide location information in the Location section.');
        scrollToSection(2);
        return false;
    }
    if (!form.featuredImagePreview) {
        alert('A featured image is required.');
        scrollToSection(4);
        return false;
    }
    return true;
};

// Location methods
const getCurrentLocation = async () => {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }

    isLoadingLocation.value = true;

    navigator.geolocation.getCurrentPosition(
        async (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            form.location.coordinates = { lat, lng };
            mapCenter.value = { lat, lng };
            mapMarker.value = { lat, lng };

            await reverseGeocode(lat, lng);

            isLoadingLocation.value = false;
        },
        (error) => {
            console.error('Error getting location:', error);
            alert('Unable to get your location. Please enter it manually.');
            isLoadingLocation.value = false;
        },
    );
};

const reverseGeocode = async (lat: number, lng: number) => {
    try {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`,
        );
        const data = await response.json();

        if (data && data.address) {
            form.location.address = data.display_name || '';
            form.location.city =
                data.address.city ||
                data.address.town ||
                data.address.village ||
                '';
            form.location.state = data.address.state || '';
            form.location.postalCode = data.address.postcode || '';
            form.location.country = data.address.country || 'United States';
        }
    } catch (error) {
        console.error('Reverse geocoding error:', error);
    }
};

const updateMapMarker = (lat: number, lng: number) => {
    mapMarker.value = { lat, lng };
    form.location.coordinates = { lat, lng };
    reverseGeocode(lat, lng);
};

const withoutClientOnlyFields = <
    T extends {
        imagePreviews?: unknown;
        featuredImagePreview?: unknown;
        existingImages?: unknown;
    },
>(
    data: T,
) => {
    const { imagePreviews, featuredImagePreview, existingImages, ...payload } =
        data;
    void imagePreviews;
    void featuredImagePreview;
    void existingImages;

    return payload;
};

const submit = () => {
    if (!validateForm()) {
        return;
    }

    // Since we're uploading files with PUT, Laravel handles this best using POST with _method field
    form.transform(withoutClientOnlyFields).post(route('pets.update', petData.value.id), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            form.transform((data) => data);
        },
    });
};
</script>

<template>
    <MainLayout class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <div class="container relative mx-auto max-w-5xl px-4 py-8">
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Edit Pet: {{ petData.name }}</h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Update the information below to keep your pet's profile current.</p>
                </div>
                <Button
                    variant="outline"
                    @click="$inertia.visit(`/pets/${petData.id}`)"
                    class="rounded-xl"
                >
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Pet Profile
                </Button>
            </div>

            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Sidebar Navigation -->
                <aside class="w-full lg:w-64 flex-shrink-0">
                    <nav class="sticky top-24 space-y-1 rounded-xl bg-white p-4 shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                        <button
                            v-for="section in sections"
                            :key="section.id"
                            @click="scrollToSection(section.id)"
                            :class="[
                                activeSection === section.id
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/50 dark:text-primary-400'
                                    : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50',
                                'group flex w-full items-center rounded-md px-3 py-2 text-sm font-medium transition-colors'
                            ]"
                        >
                            <component
                                :is="section.icon"
                                :class="[
                                    activeSection === section.id ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500',
                                    '-ml-1 mr-3 h-5 w-5 flex-shrink-0'
                                ]"
                            />
                            <span class="truncate">{{ section.name }}</span>
                        </button>
                    </nav>
                </aside>

                <!-- Form Content -->
                <div class="flex-1">
                    <form @submit.prevent="submit" class="space-y-10">
                        <!-- Section 1: Basic & Personality -->
                        <div id="section-basic" class="scroll-mt-24 space-y-6 rounded-2xl bg-white p-6 sm:p-8 shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                            <div>
                                <h2 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Basic Information & Personality</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update the primary details and description of your pet.</p>
                            </div>
                            
                            <hr class="border-gray-200 dark:border-gray-700" />
                            
                            <BasicInfoStep
                                :form="form"
                                :categories="categories"
                                :breeds="breeds"
                                :listing-types="listingTypes"
                                class="-mx-6 px-6"
                            />

                            <div class="mt-8 pt-8 border-t border-gray-100 dark:border-gray-700">
                                <h3 class="text-md font-medium text-gray-900 dark:text-white mb-4">Personality Traits & Description</h3>
                                <PersonalityStep
                                    :form="form"
                                    :pet-traits="petTraits"
                                    class="-mx-6 px-6"
                                />
                            </div>

                            <div class="mt-8 pt-8 border-t border-gray-100 dark:border-gray-700">
                                <h3 class="text-md font-medium text-gray-900 dark:text-white mb-4">Additional Info (Optional)</h3>
                                <AdditionalInfoStep
                                    :form="form"
                                    @add-info-field="addInfoField"
                                    @remove-info-field="removeInfoField"
                                    class="-mx-6 px-6"
                                />
                            </div>
                        </div>

                        <!-- Section 2: Location -->
                        <div id="section-location" class="scroll-mt-24 space-y-6 rounded-2xl bg-white p-6 sm:p-8 shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                            <div>
                                <h2 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Location Details</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Where is the pet currently located?</p>
                            </div>
                            
                            <hr class="border-gray-200 dark:border-gray-700" />
                            
                            <LocationStep
                                :is-visible="true"
                                :form="form"
                                :map-center="mapCenter"
                                :map-marker="mapMarker"
                                :is-loading-location="isLoadingLocation"
                                @get-current-location="getCurrentLocation"
                                @update-location="updateMapMarker"
                                class="-mx-6 px-6"
                            />
                        </div>

                        <!-- Section 3: Health -->
                        <div id="section-health" class="scroll-mt-24 space-y-6 rounded-2xl bg-white p-6 sm:p-8 shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                            <div>
                                <h2 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Health & Medical History</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Provide medical details, vaccinations, and overall health status.</p>
                            </div>
                            
                            <hr class="border-gray-200 dark:border-gray-700" />
                            
                            <HealthStep :form="form" class="-mx-6 px-6" />

                            <div class="mt-8 pt-8 border-t border-gray-100 dark:border-gray-700">
                                <h3 class="text-md font-medium text-gray-900 dark:text-white mb-4">Detailed Healthcare</h3>
                                <HealthcareStep :form="form" class="-mx-6 px-6" />
                            </div>
                        </div>

                        <!-- Section 4: Media -->
                        <div id="section-media" class="scroll-mt-24 space-y-6 rounded-2xl bg-white p-6 sm:p-8 shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                            <div>
                                <h2 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Media & Photos</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update photos of your pet. Changes here will apply upon save.</p>
                            </div>
                            
                            <hr class="border-gray-200 dark:border-gray-700" />
                            
                            <PhotosStep
                                :form="form"
                                @handle-file-upload="handleFileUpload"
                                @handle-featured-image-upload="handleFeaturedImageUpload"
                                @remove-featured-image="removeFeaturedImage"
                                @remove-image="removeImage"
                                class="-mx-6 px-6"
                            />
                        </div>

                        <!-- Sticky Action Bar -->
                        <div class="sticky bottom-14 z-10 mt-12 bg-transparent">
                            <div class="rounded-2xl border border-gray-200 bg-white/95 p-5 shadow-2xl backdrop-blur-md dark:border-gray-700 dark:bg-gray-800/95 flex justify-end gap-4 items-center">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="$inertia.visit(`/pets/${petData.id}`)"
                                    class="rounded-xl px-6"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="relative overflow-hidden rounded-xl px-8 py-2 font-medium shadow-md transition-all hover:shadow-lg disabled:cursor-not-allowed bg-primary hover:bg-primary/90 min-w-[200px]"
                                >
                                    <span v-if="form.processing" class="flex items-center text-white">
                                        <svg class="-ml-1 mr-2 h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Saving Changes...
                                    </span>
                                    <span v-else class="flex items-center text-white">
                                        <Check class="mr-2 h-5 w-5" />
                                        Save Changes
                                    </span>
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
