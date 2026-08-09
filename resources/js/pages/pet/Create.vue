<script setup lang="ts">
import { Button } from '@/components/ui/button';
import MainLayout from '@/layouts/MainLayout.vue';
import { useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Camera,
    Check,
    FileText,
    Heart,
    Home,
    Info,
    MapPin,
    Smile,
    Stethoscope,
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
import { useTranslations } from '@/composables/useTranslations';

interface Props {
    petCategories: { data: any[] };
    listingTypes: Array<{ value: number; label: string }>;
}

const props = defineProps<Props>();

const { t } = useTranslations();

console.log(props.petCategories.data);

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

// Pet traits — keep English IDs for data, translate labels for display
const petTraits = computed(() => [
    { id: 'Friendly', label: t('traits.friendly') },
    { id: 'Playful', label: t('traits.playful') },
    { id: 'Calm', label: t('traits.calm') },
    { id: 'Energetic', label: t('traits.energetic') },
    { id: 'Shy', label: t('traits.shy') },
    { id: 'Loyal', label: t('traits.loyal') },
    { id: 'Smart', label: t('traits.smart') },
    { id: 'Gentle', label: t('traits.gentle') },
    { id: 'Affectionate', label: t('traits.affectionate') },
    { id: 'Independent', label: t('traits.independent') },
    { id: 'Intelligent', label: t('traits.intelligent') },
]);

// Stepper state
const currentStep = ref(1);
const totalSteps = 8;
const completedSteps = ref<number[]>([]);

// Map state
const mapCenter = ref({ lat: 0, lng: 0 });
const mapMarker = ref({ lat: 0, lng: 0 });
const isLoadingLocation = ref(false);

// Form state
const form = useForm({
    name: 'Bella',
    type: '',
    breed: '',
    age: '2.5',
    color: 'Golden',
    weight: '15',
    gender: 'female',
    description:
        'Bella is a very lovely and friendly companion who loves to play fetch and go for long walks. She is great with kids and other pets.',
    listing_type: props.listingTypes[0]?.value || 1, // Default to first available type
    price: '250',
    status: 'available',
    location: {
        address: '123 Main St',
        detailedAddress: 'Apt 4B',
        city: 'New York',
        state: 'NY',
        postalCode: '10001',
        country: 'United States',
        coordinates: { lat: 40.7128, lng: -74.006 },
    },
    images: [] as File[],
    imagePreviews: [] as string[],
    featuredImage: null as File | null,
    featuredImagePreview: '' as string,
    health: {
        status: 'healthy',
        vaccinated: true,
        spayedNeutered: true,
        specialNeeds: 'None',
        lastVetVisit: '2023-10-10',
        vaccinations: [{ date: '2023-10-10', name: 'Rabies' }] as {
            date: string;
            name: string;
        }[],
        medications: [{ name: 'Heartworm preventative', usage: 'Monthly' }] as {
            name: string;
            usage: string;
        }[],
        allergies: ['Chicken'] as string[],
        vetName: 'Dr. Smith',
        vetPhone: '555-0199',
    },
    traits: ['Friendly', 'Playful'],
    additionalInfo: [{ key: 'Favorite Toy', value: 'Squeaky bone' }],
});

// Image compression configuration
// CONFIGURABLE: Adjust this value to change the maximum total upload size (in MB)
const MAX_TOTAL_SIZE_MB = 0.5;
const MAX_TOTAL_SIZE_BYTES = MAX_TOTAL_SIZE_MB * 1024 * 1024;

// Function to compress image with intelligent sizing
const compressImage = async (
    file: File,
    targetSizeKB: number,
): Promise<File> => {
    const options = {
        maxSizeMB: targetSizeKB / 1024,
        maxWidthOrHeight: 1920,
        useWebWorker: true,
        fileType: file.type as any,
    };

    try {
        const compressedFile = await imageCompression(file, options);
        return compressedFile;
    } catch (error) {
        console.error('Image compression failed:', error);
        return file; // Return original if compression fails
    }
};

// Function to calculate target size for each image
const calculateTargetSize = (totalImages: number): number => {
    // Reserve slightly less to account for overhead
    const availableSize = MAX_TOTAL_SIZE_BYTES * 0.9;
    return Math.floor(availableSize / totalImages / 1024); // Convert to KB
};

// Step configuration
const steps = computed(() => [
    {
        id: 1,
        name: t('wizard.step_basic_info'),
        icon: Home,
        description: t('wizard.step_basic_info_desc'),
    },
    {
        id: 2,
        name: t('wizard.step_location'),
        icon: MapPin,
        description: t('wizard.step_location_desc'),
    },
    {
        id: 3,
        name: t('wizard.step_photos'),
        icon: Camera,
        description: t('wizard.step_photos_desc'),
    },
    {
        id: 4,
        name: t('wizard.step_health'),
        icon: Stethoscope,
        description: t('wizard.step_health_desc'),
    },
    {
        id: 5,
        name: t('wizard.step_personality'),
        icon: Smile,
        description: t('wizard.step_personality_desc'),
    },
    {
        id: 6,
        name: t('wizard.step_details'),
        icon: Info,
        description: t('wizard.step_details_desc'),
    },
    {
        id: 7,
        name: t('wizard.step_healthcare'),
        icon: Heart,
        description: t('wizard.step_healthcare_desc'),
    },
    {
        id: 8,
        name: t('wizard.step_review'),
        icon: FileText,
        description: t('wizard.step_review_desc'),
    },
]);

// Computed properties
const invalidSteps = computed(() => {
    const errors = Object.keys(form.errors);
    const stepsWithErrors = new Set<number>();

    errors.forEach((key) => {
        // Step 1: Basic Info
        if (
            [
                'name',
                'type',
                'breed',
                'age',
                'gender',
                'color',
                'weight',
                'listing_type',
                'price',
            ].includes(key)
        ) {
            stepsWithErrors.add(1);
        }

        // Step 2: Location
        if (key.startsWith('location.')) {
            stepsWithErrors.add(2);
        }

        // Step 3: Photos
        if (
            key === 'images' ||
            key.startsWith('images.') ||
            key === 'featuredImage'
        ) {
            stepsWithErrors.add(3);
        }

        // Step 4: Health (Basic Status)
        if (
            [
                'health.status',
                'health.vaccinated',
                'health.spayedNeutered',
                'health.specialNeeds',
                'health.lastVetVisit',
            ].includes(key)
        ) {
            stepsWithErrors.add(4);
        }

        // Step 5: Personality
        if (key === 'traits' || key === 'description') {
            stepsWithErrors.add(5);
        }

        // Step 6: Additional Info
        if (key === 'additionalInfo' || key.startsWith('additionalInfo.')) {
            stepsWithErrors.add(6);
        }

        // Step 7: Healthcare (Detailed)
        if (
            key.startsWith('health.vaccinations') ||
            key.startsWith('health.medications') ||
            key.startsWith('health.allergies') ||
            key === 'health.vetName' ||
            key === 'health.vetPhone'
        ) {
            stepsWithErrors.add(7);
        }
    });

    const invalidStepsArray = Array.from(stepsWithErrors);
    return invalidStepsArray;
});

// Methods
const addInfoField = () => {
    form.additionalInfo.push({ key: '', value: '' });
};

const removeInfoField = (index: number) => {
    form.additionalInfo.splice(index, 1);
};

const handleFileUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);

    if (files.length + form.images.length > 3) {
        alert(t('pets.alert_max_upload_images'));
        return;
    }

    // Calculate total images (including featured image)
    const totalImages =
        files.length + form.images.length + (form.featuredImage ? 1 : 0);
    const targetSizeKB = calculateTargetSize(totalImages);

    for (const file of files) {
        const compressedFile = await compressImage(file, targetSizeKB);
        form.images.push(compressedFile);

        const reader = new FileReader();
        reader.onload = (e) => {
            form.imagePreviews.push(e.target?.result as string);
        };
        reader.readAsDataURL(compressedFile);
    }

    // Clear the input to allow re-uploading the same file
    target.value = '';
};

const handleFeaturedImageUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        // Calculate target size based on total images
        const totalImages = form.images.length + 1; // +1 for this featured image
        const targetSizeKB = calculateTargetSize(totalImages);

        const compressedFile = await compressImage(file, targetSizeKB);
        form.featuredImage = compressedFile;

        const reader = new FileReader();
        reader.onload = (e) => {
            form.featuredImagePreview = e.target?.result as string;
        };
        reader.readAsDataURL(compressedFile);
    }

    // Clear the input
    target.value = '';
};

const removeFeaturedImage = () => {
    form.featuredImage = null;
    form.featuredImagePreview = '';
};

const removeImage = (index: number) => {
    form.images.splice(index, 1);
    form.imagePreviews.splice(index, 1);
};

// Stepper navigation
const goToStep = async (step: number) => {
    if (step < 1 || step > totalSteps) return;

    // Validate current step before moving forward
    if (step > currentStep.value && !validateStep(currentStep.value)) {
        return;
    }

    // Mark current step as completed if moving forward
    if (
        step > currentStep.value &&
        !completedSteps.value.includes(currentStep.value)
    ) {
        completedSteps.value.push(currentStep.value);
    }

    currentStep.value = step;
    await nextTick();

    // Scroll to step
    const element = document.getElementById(`step-${step}`);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const nextStep = () => {
    if (currentStep.value < totalSteps) {
        goToStep(currentStep.value + 1);
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        goToStep(currentStep.value - 1);
    }
};

// Validation for final submission (can be made stricter)
const validateStep = (step: number): boolean => {
    switch (step) {
        case 1:
            // Basic validation - all fields required except weight
            // Note: description is in Step 5
            const basicValid = !!(
                form.name &&
                form.type &&
                form.breed &&
                form.age &&
                form.gender &&
                form.color
            );

            // Price validation if listing type is for sale
            if (form.listing_type === 2 && !form.price) {
                return false;
            }

            return basicValid;
        case 2:
            // Location - address, city, state, country required
            return !!(
                form.location.address &&
                form.location.city &&
                form.location.state &&
                form.location.country
            );
        case 3:
        // Photos - featured image required
        // return !!form.featuredImage;
        case 4:
            return true;
        case 5:
            // Personality - description is required
            return !!form.description;
        case 6:
        case 7:
        case 8:
            return true;
        default:
            return true;
    }
};

// Strict validation for form submission
const validateForm = (): boolean => {
    if (!form.name || !form.type || !form.breed || !form.age || !form.gender) {
        console.log(form.name, form.type, form.breed, form.age, form.gender);
        alert(t('pets.alert_fill_basic'));
        goToStep(1);
        return false;
    }
    if (!form.location.city || !form.location.country) {
        alert(t('pets.alert_provide_location'));
        goToStep(2);
        return false;
    }
    if (!form.featuredImage) {
        alert(t('pets.alert_upload_featured'));
        goToStep(3);
        return false;
    }
    return true;
};

// Location methods
const getCurrentLocation = async () => {
    if (!navigator.geolocation) {
        alert(t('pets.alert_geolocation_unsupported'));
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

            // Reverse geocode to get address
            await reverseGeocode(lat, lng);

            isLoadingLocation.value = false;
        },
        (error) => {
            console.error('Error getting location:', error);
            alert(t('pets.alert_unable_get_location'));
            isLoadingLocation.value = false;
        },
    );
};

const reverseGeocode = async (lat: number, lng: number) => {
    try {
        // Using OpenStreetMap Nominatim API for reverse geocoding
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

// Initialize map on mount
onMounted(() => {
    // Set default location (e.g., US center)
    mapCenter.value = { lat: 39.8283, lng: -98.5795 };
    mapMarker.value = { lat: 39.8283, lng: -98.5795 };

    // Auto-fetch current location
    getCurrentLocation();
});

const withoutClientOnlyFields = <
    T extends { imagePreviews?: unknown; featuredImagePreview?: unknown },
>(
    data: T,
) => {
    const { imagePreviews, featuredImagePreview, ...payload } = data;
    void imagePreviews;
    void featuredImagePreview;

    return payload;
};

const submit = () => {
    // Validate form before submission
    if (!validateForm()) {
        return;
    }

    form.transform(withoutClientOnlyFields).post(route('pets.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onFinish: () => {
            form.transform((data) => data);
        },
    });
};
</script>

<template>
    <MainLayout class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <div class="container relative mx-auto max-w-4xl px-4 py-8">
            <!-- Stepper Progress -->
            <StepperProgress
                :steps="steps"
                :current-step="currentStep"
                :total-steps="totalSteps"
                :completed-steps="completedSteps"
                :invalid-steps="invalidSteps"
                @go-to-step="goToStep"
            />

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Step 1: Basic Information -->
                <BasicInfoStep
                    v-show="currentStep === 1"
                    :form="form"
                    :categories="categories"
                    :breeds="breeds"
                    :listing-types="listingTypes"
                />

                <!-- Step 2: Location -->
                <LocationStep
                    v-show="currentStep === 2"
                    :is-visible="currentStep === 2"
                    :form="form"
                    :map-center="mapCenter"
                    :map-marker="mapMarker"
                    :is-loading-location="isLoadingLocation"
                    @get-current-location="getCurrentLocation"
                    @update-location="updateMapMarker"
                />

                <!-- Step 3: Photos -->
                <PhotosStep
                    v-show="currentStep === 3"
                    :form="form"
                    @handle-file-upload="handleFileUpload"
                    @handle-featured-image-upload="handleFeaturedImageUpload"
                    @remove-featured-image="removeFeaturedImage"
                    @remove-image="removeImage"
                />

                <!-- Step 4: Health -->
                <HealthStep v-show="currentStep === 4" :form="form" />

                <!-- Step 5: Personality -->
                <PersonalityStep
                    v-show="currentStep === 5"
                    :form="form"
                    :pet-traits="petTraits"
                />

                <!-- Step 6: Additional Info -->
                <AdditionalInfoStep
                    v-show="currentStep === 6"
                    :form="form"
                    @add-info-field="addInfoField"
                    @remove-info-field="removeInfoField"
                />

                <!-- Step 7: Healthcare -->
                <HealthcareStep v-show="currentStep === 7" :form="form" />

                <!-- Step 8: Review -->
                <ReviewStep
                    v-show="currentStep === 8"
                    :form="form"
                    :pet-traits="petTraits"
                    :categories="categories"
                    :breeds="breeds"
                    :listing-types="listingTypes"
                />

                <!-- Modern Form Navigation -->
                <div class="sticky bottom-6 z-10 mt-12">
                    <div
                        class="rounded-2xl border-2 border-gray-200 bg-white/90 p-5 shadow-2xl backdrop-blur-xl dark:border-gray-700 dark:bg-gray-800/90"
                    >
                        <div
                            class="flex flex-col items-center justify-between gap-4 sm:flex-row"
                        >
                            <!-- Cancel Button -->
                            <Button
                                type="button"
                                variant="ghost"
                                @click="
                                    $inertia.visit(
                                        route('settings.profile.edit'),
                                    )
                                "
                                class="group w-full rounded-xl px-6 py-3 text-sm font-medium transition-all duration-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 sm:w-auto"
                            >
                                <ArrowLeft
                                    class="me-2 h-4 w-4 transition-transform group-hover:-translate-x-1 rtl:rotate-180 rtl:group-hover:translate-x-1"
                                />
                                <span>{{ t('wizard.back_to_profile') }}</span>
                            </Button>

                            <!-- Navigation Buttons -->
                            <div
                                class="flex w-full items-center gap-3 sm:w-auto"
                            >
                                <!-- Previous Button -->
                                <Button
                                    v-if="currentStep > 1"
                                    type="button"
                                    variant="outline"
                                    @click="prevStep"
                                    class="flex-1 rounded-xl border-2 border-gray-300 px-6 py-3 text-sm font-medium transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-md dark:border-gray-600 dark:hover:bg-gray-700/50 sm:flex-none"
                                >
                                    <ArrowLeft
                                        class="me-2 h-4 w-4 rtl:rotate-180"
                                    />
                                    {{ t('wizard.previous') }}
                                </Button>

                                <!-- Next Button -->
                                <Button
                                    v-if="currentStep < totalSteps"
                                    type="button"
                                    @click="nextStep"
                                    :disabled="!validateStep(currentStep)"
                                    class="relative flex-1 overflow-hidden rounded-xl px-8 py-3 text-sm font-medium shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 disabled:hover:shadow-md sm:flex-none"
                                >
                                    <span
                                        class="relative z-10 flex items-center justify-center font-semibold text-white"
                                    >
                                        {{ t('wizard.next_step') }}
                                        <ArrowRight
                                            class="ms-2 h-4 w-4 rtl:rotate-180"
                                        />
                                    </span>
                                    <span
                                        class="absolute inset-0 bg-gradient-to-r from-primary-600 via-purple-600 to-pink-600"
                                        :class="{
                                            'opacity-50':
                                                !validateStep(currentStep),
                                        }"
                                    ></span>
                                    <span
                                        class="absolute inset-0 bg-gradient-to-r from-primary-500 via-purple-500 to-pink-500 opacity-0 transition-opacity duration-300 hover:opacity-100"
                                        :class="{
                                            hidden: !validateStep(currentStep),
                                        }"
                                    ></span>
                                </Button>

                                <!-- Submit Button -->
                                <Button
                                    v-else
                                    type="submit"
                                    :disabled="form.processing"
                                    class="relative flex-1 overflow-hidden rounded-xl px-8 py-3 text-sm font-medium shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
                                >
                                    <span
                                        class="relative z-10 flex items-center justify-center font-semibold text-white"
                                    >
                                        <span
                                            v-if="form.processing"
                                            class="flex items-center"
                                        >
                                            <svg
                                                class="-ms-1 me-2 h-5 w-5 animate-spin"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle
                                                    class="opacity-25"
                                                    cx="12"
                                                    cy="12"
                                                    r="10"
                                                    stroke="currentColor"
                                                    stroke-width="4"
                                                ></circle>
                                                <path
                                                    class="opacity-75"
                                                    fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                ></path>
                                            </svg>
                                            {{ t('wizard.creating') }}
                                        </span>
                                        <span v-else class="flex items-center">
                                            <Check class="me-2 h-5 w-5" />
                                            {{ t('wizard.create_pet_listing') }}
                                        </span>
                                    </span>
                                    <span
                                        class="absolute inset-0 bg-gradient-to-r from-green-600 via-emerald-600 to-teal-600"
                                    ></span>
                                    <span
                                        class="absolute inset-0 bg-gradient-to-r from-green-500 via-emerald-500 to-teal-500 opacity-0 transition-opacity duration-300 hover:opacity-100"
                                    ></span>
                                </Button>
                            </div>
                        </div>

                        <!-- Progress Indicator -->
                        <div
                            class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700"
                        >
                            <div
                                class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400"
                            >
                                <span>{{
                                    t('wizard.steps_completed', {
                                        completed: completedSteps.length,
                                        total: totalSteps,
                                    })
                                }}</span>
                                <span
                                    class="font-semibold text-primary-600 dark:text-primary-400"
                                >
                                    {{
                                        t('wizard.percent_complete', {
                                            percent: Math.round(
                                                (completedSteps.length /
                                                    totalSteps) *
                                                    100,
                                            ),
                                        })
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </MainLayout>
</template>

<style scoped>
/* Fix text blurriness on hover/scale transforms */
:deep(.group:hover),
:deep(.card:hover),
:deep([class*='hover:scale']:hover) {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    transform-style: preserve-3d;
    -webkit-transform-style: preserve-3d;
}

/* Prevent text blur on all transform elements */
:deep(*[class*='transition']),
:deep(*[class*='transform']),
:deep(*[class*='scale']) {
    will-change: transform;
    transform: translateZ(0);
    -webkit-transform: translateZ(0);
}

/* Animations */
@keyframes float-slow {
    0%,
    100% {
        transform: translate(0, 0) scale(1);
        opacity: 0.8;
    }
    50% {
        transform: translate(10px, 10px) scale(1.05);
        opacity: 1;
    }
}

@keyframes float-medium {
    0%,
    100% {
        transform: translate(0, 0) scale(1);
        opacity: 0.8;
    }
    50% {
        transform: translate(-10px, -15px) scale(1.1);
        opacity: 1;
    }
}

.animate-float-slow {
    animation: float-slow 15s ease-in-out infinite;
}

.animate-float-medium {
    animation: float-medium 12s ease-in-out infinite 2s;
}

/* Form Styles */
.step-container {
    animation: fadeInUp 0.5s ease-out forwards;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeInUp 0.5s ease-out forwards;
}

/* Bounce animation for completed steps */
@keyframes bounce-once {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}

.animate-bounce-once {
    animation: bounce-once 0.6s ease-in-out;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background-color: rgb(243 244 246);
    border-radius: 9999px;
}

:deep(.dark) ::-webkit-scrollbar-track {
    background-color: rgb(31 41 55);
}

::-webkit-scrollbar-thumb {
    background-color: rgb(209 213 219);
    border-radius: 9999px;
}

::-webkit-scrollbar-thumb:hover {
    background-color: rgb(156 163 175);
}

:deep(.dark) ::-webkit-scrollbar-thumb {
    background-color: rgb(75 85 99);
}

:deep(.dark) ::-webkit-scrollbar-thumb:hover {
    background-color: rgb(107 114 128);
}

/* Form Field Focus Styles */
:deep(.form-input:focus),
:deep(.form-select:focus),
:deep(.form-textarea:focus) {
    outline: 2px solid transparent;
    outline-offset: 2px;
    border-color: rgb(165 180 252);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

:deep(.dark .form-input:focus),
:deep(.dark .form-select:focus),
:deep(.dark .form-textarea:focus) {
    border-color: rgb(99 102 241);
}

/* File Upload Area */
.file-upload-area {
    border: 2px dashed rgb(209 213 219);
    border-radius: 0.75rem;
    padding: 2rem;
    text-align: center;
    transition: colors 200ms;
    cursor: pointer;
}

.file-upload-area:hover {
    border-color: rgb(165 180 252);
    background-color: rgba(238, 242, 255, 0.3);
}

:deep(.dark) .file-upload-area {
    border-color: rgb(75 85 99);
}

:deep(.dark) .file-upload-area:hover {
    border-color: rgb(99 102 241);
    background-color: rgba(49, 46, 129, 0.1);
}

.file-upload-area.dragover {
    border-color: rgb(99 102 241);
    background-color: rgba(238, 242, 255, 0.5);
}

:deep(.dark) .file-upload-area.dragover {
    background-color: rgba(49, 46, 129, 0.2);
}

/* Responsive Adjustments */
@media (max-width: 640px) {
    .container {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }

    .card {
        padding: 1rem;
    }
}

/* Fade In Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in {
    animation: fadeIn 0.3s ease-out forwards;
}

/* Custom Checkbox Style */
:deep(.custom-checkbox:checked) {
    background-color: rgb(79 70 229);
    border-color: rgb(79 70 229);
}

/* Custom Radio Buttons */
:deep(.custom-radio:checked) {
    border-color: rgb(99 102 241);
    box-shadow:
        0 0 0 2px white,
        0 0 0 4px #6366f1;
}
</style>
