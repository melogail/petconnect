<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, X, MapPin, Camera, ArrowLeft, ArrowRight, Check, Home, Heart, Stethoscope, Smile, Info, FileText } from 'lucide-vue-next';

// Pet categories and breeds
const categories = [
    { id: 'dog', name: 'Dog' },
    { id: 'cat', name: 'Cat' },
    { id: 'bird', name: 'Bird' },
    { id: 'other', name: 'Other' },
];

const breeds = {
    dog: [
        { id: 'labrador', name: 'Labrador Retriever' },
        { id: 'german_shepherd', name: 'German Shepherd' },
        { id: 'golden_retriever', name: 'Golden Retriever' },
        { id: 'bulldog', name: 'Bulldog' },
        { id: 'beagle', name: 'Beagle' },
    ],
    cat: [
        { id: 'siamese', name: 'Siamese' },
        { id: 'persian', name: 'Persian' },
        { id: 'maine_coon', name: 'Maine Coon' },
        { id: 'ragdoll', name: 'Ragdoll' },
        { id: 'bengal', name: 'Bengal' },
    ],
    bird: [
        { id: 'parakeet', name: 'Parakeet' },
        { id: 'cockatiel', name: 'Cockatiel' },
        { id: 'lovebird', name: 'Lovebird' },
        { id: 'canary', name: 'Canary' },
    ],
    other: [
        { id: 'other', name: 'Other' },
    ],
};

// Pet traits
const petTraits = [
    { id: 'friendly', label: 'Friendly' },
    { id: 'playful', label: 'Playful' },
    { id: 'calm', label: 'Calm' },
    { id: 'energetic', label: 'Energetic' },
    { id: 'shy', label: 'Shy' },
    { id: 'affectionate', label: 'Affectionate' },
    { id: 'independent', label: 'Independent' },
    { id: 'intelligent', label: 'Intelligent' },
];

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
    name: '',
    type: '',
    breed: '',
    age: '',
    gender: '',
    description: '',
    location: {
        address: '',
        detailedAddress: '',
        city: '',
        state: '',
        postalCode: '',
        country: 'United States',
        coordinates: { lat: 0, lng: 0 },
    },
    images: [] as File[],
    imagePreviews: [] as string[],
    featuredImage: null as File | null,
    featuredImagePreview: '' as string,
    health: {
        status: 'healthy',
        vaccinated: false,
        spayedNeutered: false,
        specialNeeds: '',
        lastVetVisit: '',
    },
    traits: [] as string[],
    additionalInfo: [{ key: '', value: '' }],
});

// Step configuration
const steps = [
    { id: 1, name: 'Basic Info', icon: Home, description: 'Pet details' },
    { id: 2, name: 'Location', icon: MapPin, description: 'Where is your pet' },
    { id: 3, name: 'Photos', icon: Camera, description: 'Upload images' },
    { id: 4, name: 'Health', icon: Stethoscope, description: 'Health status' },
    { id: 5, name: 'Personality', icon: Smile, description: 'Traits & behavior' },
    { id: 6, name: 'Details', icon: Info, description: 'Additional info' },
    { id: 7, name: 'Healthcare', icon: Heart, description: 'Medical history' },
    { id: 8, name: 'Review', icon: FileText, description: 'Final check' },
];

// Computed properties
const filteredBreeds = computed(() => {
    return form.type ? breeds[form.type as keyof typeof breeds] : [];
});

const isMaxImages = computed(() => form.images.length >= 3);

// Methods
const addInfoField = () => {
    form.additionalInfo.push({ key: '', value: '' });
};

const removeInfoField = (index: number) => {
    form.additionalInfo.splice(index, 1);
};

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);
    
    if (files.length + form.images.length > 3) {
        alert('You can only upload up to 3 images');
        return;
    }

    files.forEach((file) => {
        form.images.push(file);
        
        const reader = new FileReader();
        reader.onload = (e) => {
            form.imagePreviews.push(e.target?.result as string);
        };
        reader.readAsDataURL(file);
    });
};

const handleFeaturedImageUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (file) {
        form.featuredImage = file;
        
        const reader = new FileReader();
        reader.onload = (e) => {
            form.featuredImagePreview = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    }
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
    if (step > currentStep.value && !completedSteps.value.includes(currentStep.value)) {
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
    switch(step) {
        case 1:
            // Basic validation - at least name is required
            return !!form.name;
        case 2:
            // Location - at least city is helpful
            return !!form.location.city;
        case 3:
            // Photos - optional but recommended
            return true; // Allow proceeding without images
        case 4:
        case 5:
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
        alert('Please fill in all basic information fields');
        goToStep(1);
        return false;
    }
    if (!form.location.city || !form.location.country) {
        alert('Please provide location information');
        goToStep(2);
        return false;
    }
    if (form.images.length === 0) {
        const proceed = confirm('No images uploaded. Do you want to continue without images?');
        if (!proceed) {
            goToStep(3);
            return false;
        }
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
            
            // Reverse geocode to get address
            await reverseGeocode(lat, lng);
            
            isLoadingLocation.value = false;
        },
        (error) => {
            console.error('Error getting location:', error);
            alert('Unable to get your location. Please enter it manually.');
            isLoadingLocation.value = false;
        }
    );
};

const reverseGeocode = async (lat: number, lng: number) => {
    try {
        // Using OpenStreetMap Nominatim API for reverse geocoding
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`
        );
        const data = await response.json();
        
        if (data && data.address) {
            form.location.address = data.display_name || '';
            form.location.city = data.address.city || data.address.town || data.address.village || '';
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
});

const submit = () => {
    // Validate form before submission
    if (!validateForm()) {
        return;
    }

    const formData = new FormData();

    // Append all form data
    Object.entries(form).forEach(([key, value]) => {
        if (key === 'images') {
            form.images.forEach((file) => {
                formData.append('images[]', file);
            });
        } else if (typeof value === 'object' && value !== null) {
            formData.append(key, JSON.stringify(value));
        } else {
            formData.append(key, value as string);
        }
    });

    form.post(route('pets.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <MainLayout class="bg-gray-50 dark:bg-gray-900 min-h-screen">
        <!-- Enhanced Animated Background with Particles -->
        <div class="fixed inset-0 -z-50 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-50/80 to-purple-50/80 dark:from-gray-900/95 dark:to-gray-800/95 transition-colors duration-500"></div>
            <div class="absolute inset-0 opacity-30 dark:opacity-5">
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSIgcGF0dGVyblRyYW5zZm9ybT0icm90YXRlKDQUpIj48cmVjdCB3aWR0aD0iNTAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InJnYmEoMCwgMCwgMCwgMC4wMikiLz48L3BhdHRlcm4+PC9kZWZzPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InVybCgjcGF0dGVybikiLz48L3N2Zz4=')] bg-repeat"></div>
            </div>
            <!-- Animated floating dots -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-1/4 -right-1/4 w-1/2 h-1/2 rounded-full bg-blue-200/20 dark:bg-blue-900/20 blur-3xl animate-float-slow"></div>
                <div class="absolute -bottom-1/4 -left-1/4 w-1/3 h-1/3 rounded-full bg-purple-200/20 dark:bg-purple-900/20 blur-3xl animate-float-medium"></div>
            </div>
        </div>

        <div class="container mx-auto py-8 px-4 max-w-4xl relative">
            <!-- Modern Stepper -->
            <div class="mb-12">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-4xl font-bold bg-gradient-to-r from-primary-600 via-purple-600 to-pink-600 bg-clip-text text-transparent mb-2">
                        Add New Pet
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">Complete all steps to create your pet listing</p>
                </div>

                <!-- Desktop Stepper -->
                <div class="hidden md:block">
                    <div class="relative">
                        <!-- Progress Line -->
                        <div class="absolute top-5 left-0 right-0 h-0.5 bg-gray-200 dark:bg-gray-700"></div>
                        <div 
                            class="absolute top-5 left-0 h-0.5 bg-gradient-to-r from-primary-500 to-purple-500 transition-all duration-500 ease-out"
                            :style="{ width: `${((currentStep - 1) / (totalSteps - 1)) * 100}%` }"
                        ></div>

                        <!-- Steps -->
                        <div class="relative flex justify-between">
                            <div 
                                v-for="step in steps" 
                                :key="step.id"
                                class="flex flex-col items-center group cursor-pointer"
                                @click="goToStep(step.id)"
                            >
                                <!-- Step Circle -->
                                <div 
                                    class="w-12 h-12 rounded-full flex items-center justify-center transition-all duration-300 mb-2 relative"
                                    :class="{
                                        'bg-gradient-to-br from-primary-500 via-purple-500 to-pink-500 text-white shadow-xl scale-110 ring-4 ring-primary-100 dark:ring-primary-900/50': currentStep === step.id,
                                        'bg-gradient-to-br from-green-500 to-emerald-600 text-white shadow-lg ring-2 ring-green-200 dark:ring-green-900/50': completedSteps.includes(step.id) && currentStep !== step.id,
                                        'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500': !completedSteps.includes(step.id) && currentStep !== step.id,
                                        'group-hover:scale-110 group-hover:shadow-lg': true
                                    }"
                                >
                                    <!-- Pulse animation for completed steps -->
                                    <div v-if="completedSteps.includes(step.id) && currentStep !== step.id" class="absolute inset-0 rounded-full bg-green-400 animate-ping opacity-20"></div>
                                    
                                    <Check v-if="completedSteps.includes(step.id) && currentStep !== step.id" class="w-6 h-6 relative z-10 animate-bounce-once" />
                                    <component v-else :is="step.icon" class="w-5 h-5 relative z-10" />
                                </div>

                                <!-- Step Label -->
                                <div class="text-center">
                                    <div 
                                        class="text-sm font-medium transition-colors duration-200"
                                        :class="{
                                            'text-primary-600 dark:text-primary-400': currentStep === step.id,
                                            'text-gray-700 dark:text-gray-300': completedSteps.includes(step.id) && currentStep !== step.id,
                                            'text-gray-400 dark:text-gray-500': !completedSteps.includes(step.id) && currentStep !== step.id
                                        }"
                                    >
                                        {{ step.name }}
                                    </div>
                                    <div class="text-xs text-gray-400 dark:text-gray-600 mt-0.5">
                                        {{ step.description }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Stepper -->
                <div class="md:hidden">
                    <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary-500 to-purple-500 flex items-center justify-center text-white">
                                    <component :is="steps[currentStep - 1].icon" class="w-5 h-5" />
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-800 dark:text-white">
                                        {{ steps[currentStep - 1].name }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Step {{ currentStep }} of {{ totalSteps }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-sm font-medium text-primary-600 dark:text-primary-400">
                                {{ Math.round((currentStep / totalSteps) * 100) }}%
                            </div>
                        </div>
                        <!-- Progress Bar -->
                        <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div 
                                class="h-full bg-gradient-to-r from-primary-500 to-purple-500 transition-all duration-500"
                                :style="{ width: `${(currentStep / totalSteps) * 100}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Step 1: Basic Information -->
                <div id="step-1" v-show="currentStep === 1" class="step-container animate-fade-in">
                    <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-primary-100/50 dark:border-primary-900/30 hover:border-primary-300 dark:hover:border-primary-700 shadow-lg hover:scale-[1.01]">
                    <!-- Animated Background Gradient -->
                    <div class="absolute -z-10 inset-0 bg-gradient-to-br from-primary-50/30 via-purple-50/20 to-pink-50/10 dark:from-primary-900/20 dark:via-purple-900/10 dark:to-pink-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <!-- Decorative Corner -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-primary-100/20 to-transparent dark:from-primary-900/10 rounded-bl-full opacity-50"></div>
                    <CardHeader class="relative z-10">
                        <div class="flex items-start sm:items-center space-x-4">
                            <div class="relative p-3 rounded-2xl bg-gradient-to-br from-primary-500 to-purple-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                                <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </div>
                            <div>
                                <CardTitle class="text-xl font-bold text-gray-800 dark:text-white">Basic Information</CardTitle>
                                <CardDescription class="text-gray-500 dark:text-gray-400">Tell us about your pet's basic details</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label for="name">Pet Name</Label>
                            <Input id="name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="type">Pet Type</Label>
                            <Select v-model="form.type" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select pet type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.type" />
                        </div>

                        <div class="space-y-2">
                            <Label for="breed">Breed</Label>
                            <Select v-model="form.breed" :disabled="!form.type" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select breed" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="breed in filteredBreeds" :key="breed.id" :value="breed.id">
                                        {{ breed.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.breed" />
                        </div>

                        <div class="space-y-2">
                            <Label for="age">Age (years)</Label>
                            <Input id="age" v-model.number="form.age" type="number" min="0" step="0.5" required />
                            <InputError :message="form.errors.age" />
                        </div>

                        <div class="space-y-2">
                            <Label>Gender</Label>
                            <div class="flex space-x-4">
                                <div class="flex items-center space-x-2">
                                    <Input
                                        id="male"
                                        v-model="form.gender"
                                        type="radio"
                                        value="male"
                                        class="h-4 w-4"
                                    />
                                    <Label for="male">Male</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <Input
                                        id="female"
                                        v-model="form.gender"
                                        type="radio"
                                        value="female"
                                        class="h-4 w-4"
                                    />
                                    <Label for="female">Female</Label>
                                </div>
                            </div>
                            <InputError :message="form.errors.gender" />
                        </div>
                    </CardContent>
                    </Card>
                </div>

                <!-- Step 2: Location -->
                <div id="step-2" v-show="currentStep === 2" class="step-container animate-fade-in">
                    <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-blue-100/50 dark:border-blue-900/30 hover:border-blue-300 dark:hover:border-blue-700 shadow-lg hover:scale-[1.01]">
                    <!-- Animated Background Gradient -->
                    <div class="absolute -z-10 inset-0 bg-gradient-to-br from-blue-50/30 via-cyan-50/20 to-sky-50/10 dark:from-blue-900/20 dark:via-cyan-900/10 dark:to-sky-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <!-- Decorative Corner -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-100/20 to-transparent dark:from-blue-900/10 rounded-bl-full opacity-50"></div>
                    <CardHeader class="relative z-10">
                        <div class="flex items-center space-x-4">
                            <div class="relative p-3 rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                                <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                                <MapPin class="h-6 w-6 relative z-10" />
                            </div>
                            <div>
                                <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Location</CardTitle>
                                <CardDescription class="text-gray-500 dark:text-gray-400">Where is your pet located?</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Get Location Button -->
                        <div class="flex justify-center">
                            <Button 
                                type="button" 
                                variant="outline" 
                                @click="getCurrentLocation"
                                :disabled="isLoadingLocation"
                                class="group relative overflow-hidden border-2 border-primary-200 dark:border-primary-800 hover:border-primary-400 dark:hover:border-primary-600 transition-all duration-300"
                            >
                                <span class="relative z-10 flex items-center">
                                    <MapPin class="w-5 h-5 mr-2 transition-transform group-hover:scale-110" :class="{ 'animate-pulse': isLoadingLocation }" />
                                    {{ isLoadingLocation ? 'Getting Location...' : 'Use My Current Location' }}
                                </span>
                                <span class="absolute inset-0 bg-gradient-to-r from-primary-50 to-purple-50 dark:from-primary-900/20 dark:to-purple-900/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                            </Button>
                        </div>

                        <!-- Interactive Map -->
                        <div class="relative rounded-xl overflow-hidden border-2 border-gray-200 dark:border-gray-700 shadow-lg">
                            <div class="h-64 bg-gradient-to-br from-blue-100 to-purple-100 dark:from-blue-900/20 dark:to-purple-900/20 relative">
                                <!-- Map Placeholder with Grid -->
                                <div class="absolute inset-0 opacity-20">
                                    <div class="grid grid-cols-8 grid-rows-8 h-full">
                                        <div v-for="i in 64" :key="i" class="border border-gray-300 dark:border-gray-600"></div>
                                    </div>
                                </div>
                                
                                <!-- Map Center Marker -->
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="relative">
                                        <div class="absolute -inset-4 bg-primary-500/20 rounded-full animate-ping"></div>
                                        <div class="relative bg-primary-600 text-white p-3 rounded-full shadow-xl">
                                            <MapPin class="w-6 h-6" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Coordinates Display -->
                                <div class="absolute bottom-3 left-3 right-3 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm rounded-lg px-3 py-2 text-xs font-mono">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600 dark:text-gray-400">Coordinates:</span>
                                        <span class="text-gray-800 dark:text-gray-200 font-semibold">
                                            {{ mapMarker.lat.toFixed(6) }}, {{ mapMarker.lng.toFixed(6) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Address Display -->
                        <div v-if="form.location.address" class="p-4 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800">
                            <div class="flex items-start space-x-3">
                                <MapPin class="w-5 h-5 text-primary-600 dark:text-primary-400 mt-0.5 flex-shrink-0" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Detected Address</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ form.location.address }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Detailed Address Input -->
                        <div class="space-y-2">
                            <Label for="detailedAddress" class="flex items-center space-x-2">
                                <span>Detailed Address</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">(Optional)</span>
                            </Label>
                            <Textarea
                                id="detailedAddress"
                                v-model="form.location.detailedAddress"
                                placeholder="Enter apartment number, building name, landmarks, or any additional details..."
                                class="min-h-[80px] resize-none"
                            />
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Add specific details to help people find your location easily
                            </p>
                        </div>

                        <!-- Location Details Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="city">City *</Label>
                                <Input 
                                    id="city" 
                                    v-model="form.location.city" 
                                    placeholder="Enter city"
                                    required 
                                />
                                <InputError :message="form.errors['location.city']" />
                            </div>
                            <div class="space-y-2">
                                <Label for="state">State/Province</Label>
                                <Input 
                                    id="state" 
                                    v-model="form.location.state" 
                                    placeholder="Enter state or province"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="postalCode">Postal Code</Label>
                                <Input 
                                    id="postalCode" 
                                    v-model="form.location.postalCode" 
                                    placeholder="Enter postal code"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="country">Country *</Label>
                                <Input 
                                    id="country" 
                                    v-model="form.location.country" 
                                    placeholder="Enter country"
                                    required 
                                />
                                <InputError :message="form.errors['location.country']" />
                            </div>
                        </div>
                    </CardContent>
                    </Card>
                </div>

                <!-- Step 3: Photos -->
                <div id="step-3" v-show="currentStep === 3" class="step-container animate-fade-in">
                    <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-amber-100/50 dark:border-amber-900/30 hover:border-amber-300 dark:hover:border-amber-700 shadow-lg hover:scale-[1.01]">
                    <!-- Animated Background Gradient -->
                    <div class="absolute -z-10 inset-0 bg-gradient-to-br from-amber-50/30 via-yellow-50/20 to-orange-50/10 dark:from-amber-900/20 dark:via-yellow-900/10 dark:to-orange-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <!-- Decorative Corner -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-amber-100/20 to-transparent dark:from-amber-900/10 rounded-bl-full opacity-50"></div>
                        <CardHeader class="relative z-10">
                            <div class="flex items-center space-x-4">
                                <div class="relative p-3 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                                    <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                                    <Camera class="h-6 w-6 relative z-10" />
                                </div>
                                <div>
                                    <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Pet Photos</CardTitle>
                                    <CardDescription class="text-gray-500 dark:text-gray-400">Upload a featured photo and up to 3 gallery images</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Featured Image Section -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label class="text-base font-semibold text-gray-800 dark:text-white">Featured Photo</Label>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Main display image</span>
                            </div>
                            <div class="relative">
                                <input
                                    type="file"
                                    id="featured-photo"
                                    class="hidden"
                                    accept="image/*"
                                    @change="handleFeaturedImageUpload"
                                />
                                <Label
                                    v-if="!form.featuredImagePreview"
                                    for="featured-photo"
                                    class="flex aspect-video items-center justify-center rounded-xl border-2 border-dashed border-primary-300 dark:border-primary-700 cursor-pointer hover:border-primary-500 dark:hover:border-primary-500 transition-all bg-primary-50/30 dark:bg-primary-900/10 hover:bg-primary-50/50 dark:hover:bg-primary-900/20"
                                >
                                    <div class="text-center p-6">
                                        <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                                            <Camera class="w-8 h-8 text-white" />
                                        </div>
                                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Upload Featured Photo</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">This will be the main image for your pet</span>
                                    </div>
                                </Label>
                                <div v-else class="relative group">
                                    <img
                                        :src="form.featuredImagePreview"
                                        alt="Featured pet photo"
                                        class="w-full aspect-video object-cover rounded-xl border-2 border-primary-200 dark:border-primary-800"
                                    />
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-xl flex items-center justify-center">
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            @click="removeFeaturedImage"
                                            class="shadow-lg"
                                        >
                                            <X class="w-4 h-4 mr-2" />
                                            Remove
                                        </Button>
                                    </div>
                                    <div class="absolute top-3 left-3 bg-amber-500 text-white px-3 py-1 rounded-full text-xs font-semibold shadow-lg">
                                        Featured
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gallery Images Section -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label class="text-base font-semibold text-gray-800 dark:text-white">Gallery Photos</Label>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Up to 3 images</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <!-- Image upload button -->
                            <div>
                                <input
                                    type="file"
                                    id="pet-photos"
                                    class="hidden"
                                    multiple
                                    accept="image/*"
                                    @change="handleFileUpload"
                                    :disabled="isMaxImages"
                                />
                                <Label
                                    for="pet-photos"
                                    class="flex aspect-square items-center justify-center rounded-lg border-2 border-dashed border-gray-300 cursor-pointer hover:border-gray-400 transition-colors"
                                    :class="{ 'opacity-50 cursor-not-allowed': isMaxImages }"
                                >
                                    <div class="text-center p-4">
                                        <Camera class="w-8 h-8 mx-auto text-gray-400" />
                                        <span class="mt-2 block text-sm text-gray-600">
                                            {{ form.images.length > 0 ? 'Add more' : 'Add photos' }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            {{ form.images.length }}/3
                                        </span>
                                    </div>
                                </Label>
                            </div>

                            <!-- Image previews -->
                            <div
                                v-for="(preview, index) in form.imagePreviews"
                                :key="index"
                                class="relative group"
                            >
                                <img
                                    :src="preview"
                                    :alt="`Pet photo ${index + 1}`"
                                    class="w-full aspect-square object-cover rounded-lg"
                                />
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="icon"
                                    class="absolute -top-2 -right-2 w-6 h-6 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                                    @click="removeImage(index)"
                                >
                                    <X class="w-3 h-3" />
                                </Button>
                            </div>
                        </div>
                        </div>
                        <InputError :message="form.errors.images" class="mt-2" />
                    </CardContent>
                    </Card>
                </div>

                <!-- Step 4: Health -->
                <div id="step-4" v-show="currentStep === 4" class="step-container animate-fade-in">
                    <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-emerald-100/50 dark:border-emerald-900/30 hover:border-emerald-300 dark:hover:border-emerald-700 shadow-lg hover:scale-[1.01]">
                    <!-- Animated Background Gradient -->
                    <div class="absolute -z-10 inset-0 bg-gradient-to-br from-emerald-50/30 via-green-50/20 to-teal-50/10 dark:from-emerald-900/20 dark:via-green-900/10 dark:to-teal-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <!-- Decorative Corner -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-emerald-100/20 to-transparent dark:from-emerald-900/10 rounded-bl-full opacity-50"></div>
                    <CardHeader class="relative z-10">
                        <div class="flex items-center space-x-4">
                            <div class="relative p-3 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                                <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Health Status</CardTitle>
                                <CardDescription class="text-gray-500 dark:text-gray-400">Your pet's current health condition</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="health-status">Overall Health Status</Label>
                            <Select id="health-status" v-model="form.health.status">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select health status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="excellent">Excellent</SelectItem>
                                    <SelectItem value="good">Good</SelectItem>
                                    <SelectItem value="fair">Fair</SelectItem>
                                    <SelectItem value="poor">Poor</SelectItem>
                                    <SelectItem value="critical">Critical</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-2">
                                <Checkbox id="vaccinated" v-model:checked="form.health.vaccinated" />
                                <Label for="vaccinated">Vaccinated</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Checkbox id="spayed-neutered" v-model:checked="form.health.spayedNeutered" />
                                <Label for="spayed-neutered">Spayed/Neutered</Label>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="last-vet-visit">Last Vet Visit</Label>
                            <Input id="last-vet-visit" type="date" v-model="form.health.lastVetVisit" />
                        </div>

                        <div class="space-y-2">
                            <Label for="special-needs">Special Needs or Medical Conditions</Label>
                            <Textarea
                                id="special-needs"
                                v-model="form.health.specialNeeds"
                                placeholder="Any special care requirements, medications, or health concerns..."
                                class="min-h-[100px]"
                            />
                        </div>
                    </CardContent>
                    </Card>
                </div>

                <!-- Step 5: Personality -->
                <div id="step-5" v-show="currentStep === 5" class="step-container animate-fade-in">
                    <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-purple-100/50 dark:border-purple-900/30 hover:border-purple-300 dark:hover:border-purple-700 shadow-lg hover:scale-[1.01]">
                    <!-- Animated Background Gradient -->
                    <div class="absolute -z-10 inset-0 bg-gradient-to-br from-purple-50/30 via-violet-50/20 to-fuchsia-50/10 dark:from-purple-900/20 dark:via-violet-900/10 dark:to-fuchsia-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <!-- Decorative Corner -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-purple-100/20 to-transparent dark:from-purple-900/10 rounded-bl-full opacity-50"></div>
                        <CardHeader class="relative z-10">
                            <div class="flex items-center space-x-4">
                                <div class="relative p-3 rounded-2xl bg-gradient-to-br from-purple-500 to-fuchsia-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                                    <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Description & Personality</CardTitle>
                                    <CardDescription class="text-gray-500 dark:text-gray-400">Tell us about your pet's personality and traits</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Tell us about your pet's personality, habits, and any special needs..."
                                class="min-h-[120px]"
                                required
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="space-y-3">
                            <Label>Personality Traits</Label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                <div v-for="trait in petTraits" :key="trait.id" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-purple-50 dark:hover:bg-purple-900/10 transition-colors">
                                    <Checkbox
                                        :id="`trait-${trait.id}`"
                                        :checked="form.traits.includes(trait.id)"
                                        @update:checked="(checked) => {
                                            if (checked) {
                                                form.traits.push(trait.id);
                                            } else {
                                                const index = form.traits.indexOf(trait.id);
                                                if (index > -1) form.traits.splice(index, 1);
                                            }
                                        }"
                                    />
                                    <Label :for="`trait-${trait.id}`" class="cursor-pointer">{{ trait.label }}</Label>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                    </Card>
                </div>

                <!-- Step 6: Additional Info -->
                <div id="step-6" v-show="currentStep === 6" class="step-container animate-fade-in">
                    <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-indigo-100/50 dark:border-indigo-900/30 hover:border-indigo-300 dark:hover:border-indigo-700 shadow-lg hover:scale-[1.01]">
                    <!-- Animated Background Gradient -->
                    <div class="absolute -z-10 inset-0 bg-gradient-to-br from-indigo-50/30 via-blue-50/20 to-cyan-50/10 dark:from-indigo-900/20 dark:via-blue-900/10 dark:to-cyan-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <!-- Decorative Corner -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-indigo-100/20 to-transparent dark:from-indigo-900/10 rounded-bl-full opacity-50"></div>
                    <CardHeader class="relative z-10">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <div class="relative p-3 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                                    <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Additional Information</CardTitle>
                                    <CardDescription class="text-gray-500 dark:text-gray-400">Add any extra details about your pet</CardDescription>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addInfoField"
                                class="relative overflow-hidden group"
                            >
                                <span class="relative z-10 flex items-center">
                                    <Plus class="w-4 h-4 mr-2 transition-transform group-hover:rotate-90" />
                                    Add Field
                                </span>
                                <span class="absolute inset-0 bg-indigo-50 dark:bg-indigo-900/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-for="(info, index) in form.additionalInfo" :key="index" class="grid grid-cols-12 gap-3 items-end">
                            <div class="col-span-5">
                                <Label :for="`key-${index}`">Key</Label>
                                <Input
                                    :id="`key-${index}`"
                                    v-model="info.key"
                                    placeholder="e.g., Microchip ID, Color"
                                />
                            </div>
                            <div class="col-span-5">
                                <Label :for="`value-${index}`">Value</Label>
                                <Input
                                    :id="`value-${index}`"
                                    v-model="info.value"
                                    placeholder="Enter value"
                                />
                            </div>
                            <div class="col-span-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="w-full h-10 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 hover:border-red-200 dark:hover:border-red-800 transition-all duration-200 group/btn"
                                    @click="removeInfoField(index)"
                                    :disabled="form.additionalInfo.length === 1"
                                >
                                    <X class="w-4 h-4 transition-transform group-hover/btn:rotate-90" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                    </Card>
                </div>

                <!-- Step 7: Healthcare -->
                <div id="step-7" v-show="currentStep === 7" class="step-container animate-fade-in">
                    <Card class="group relative overflow-hidden transition-all duration-500 hover:shadow-2xl dark:bg-gray-800/70 backdrop-blur-md border-2 border-rose-100/50 dark:border-rose-900/30 hover:border-rose-300 dark:hover:border-rose-700 shadow-lg hover:scale-[1.01]">
                    <!-- Animated Background Gradient -->
                    <div class="absolute -z-10 inset-0 bg-gradient-to-br from-rose-50/30 via-pink-50/20 to-red-50/10 dark:from-rose-900/20 dark:via-pink-900/10 dark:to-red-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <!-- Decorative Corner -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-rose-100/20 to-transparent dark:from-rose-900/10 rounded-bl-full opacity-50"></div>
                    <CardHeader class="relative z-10">
                        <div class="flex items-center space-x-4">
                            <div class="relative p-3 rounded-2xl bg-gradient-to-br from-rose-500 to-pink-600 text-white shadow-lg group-hover:shadow-xl group-hover:scale-110 transition-all duration-300">
                                <div class="absolute inset-0 bg-white/20 rounded-2xl animate-pulse"></div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                            </div>
                            <div>
                                <CardTitle class="text-xl font-semibold text-gray-800 dark:text-white">Healthcare Information</CardTitle>
                                <CardDescription class="text-gray-500 dark:text-gray-400">Your pet's medical history and care</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="vaccination-records">Vaccination Records</Label>
                            <Textarea
                                id="vaccination-records"
                                placeholder="List all vaccinations and their dates"
                                class="min-h-[100px]"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="medications">Current Medications</Label>
                            <Textarea
                                id="medications"
                                placeholder="List any current medications, dosages, and schedules"
                                class="min-h-[80px]"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="allergies">Allergies</Label>
                            <Textarea
                                id="allergies"
                                placeholder="List any known allergies or adverse reactions"
                                class="min-h-[80px]"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="veterinarian">Veterinarian Information</Label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <Input id="vet-name" placeholder="Veterinarian Name" />
                                </div>
                                <div>
                                    <Input id="vet-phone" placeholder="Phone Number" type="tel" />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                    </Card>
                </div>

                <!-- Step 8: Review -->
                <div id="step-8" v-show="currentStep === 8" class="step-container animate-fade-in">
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
                            </div>
                        </div>

                        <!-- Location Review -->
                        <div class="p-4 rounded-xl bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 border border-blue-200 dark:border-blue-800">
                            <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-3 flex items-center">
                                <MapPin class="w-5 h-5 mr-2 text-blue-600 dark:text-blue-400" />
                                Location
                            </h3>
                            <div class="space-y-2 text-sm">
                                <div><span class="text-gray-500 dark:text-gray-400">City:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.location.city || 'Not provided' }}</span></div>
                                <div v-if="form.location.state"><span class="text-gray-500 dark:text-gray-400">State:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.location.state }}</span></div>
                                <div><span class="text-gray-500 dark:text-gray-400">Country:</span> <span class="font-medium text-gray-800 dark:text-white">{{ form.location.country }}</span></div>
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

                <!-- Modern Form Navigation -->
                <div class="sticky bottom-6 z-10 mt-12">
                    <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-xl border-2 border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-2xl">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                            <!-- Cancel Button -->
                            <Button
                                type="button"
                                variant="ghost"
                                @click="$inertia.visit(route('profile.edit'))"
                                class="group px-6 py-3 text-sm font-medium transition-all duration-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-xl w-full sm:w-auto"
                            >
                                <ArrowLeft class="w-4 h-4 mr-2 transition-transform group-hover:-translate-x-1" />
                                <span>Back to Profile</span>
                            </Button>
                            
                            <!-- Navigation Buttons -->
                            <div class="flex items-center gap-3 w-full sm:w-auto">
                                <!-- Previous Button -->
                                <Button
                                    v-if="currentStep > 1"
                                    type="button"
                                    variant="outline"
                                    @click="prevStep"
                                    class="flex-1 sm:flex-none px-6 py-3 text-sm font-medium transition-all duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 border-2 border-gray-300 dark:border-gray-600 rounded-xl hover:shadow-md hover:-translate-y-0.5"
                                >
                                    <ArrowLeft class="w-4 h-4 mr-2" />
                                    Previous
                                </Button>
                                
                                <!-- Next Button -->
                                <Button
                                    v-if="currentStep < totalSteps"
                                    type="button"
                                    @click="nextStep"
                                    class="flex-1 sm:flex-none relative overflow-hidden px-8 py-3 text-sm font-medium transition-all duration-300 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-0.5"
                                >
                                    <span class="relative z-10 flex items-center justify-center text-white font-semibold">
                                        Next Step
                                        <ArrowRight class="w-4 h-4 ml-2" />
                                    </span>
                                    <span class="absolute inset-0 bg-gradient-to-r from-primary-600 via-purple-600 to-pink-600"></span>
                                    <span class="absolute inset-0 bg-gradient-to-r from-primary-500 via-purple-500 to-pink-500 opacity-0 hover:opacity-100 transition-opacity duration-300"></span>
                                </Button>
                                
                                <!-- Submit Button -->
                                <Button
                                    v-else
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex-1 sm:flex-none relative overflow-hidden px-8 py-3 text-sm font-medium transition-all duration-300 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span class="relative z-10 flex items-center justify-center text-white font-semibold">
                                        <span v-if="form.processing" class="flex items-center">
                                            <svg class="animate-spin -ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Creating...
                                        </span>
                                        <span v-else class="flex items-center">
                                            <Check class="w-5 h-5 mr-2" />
                                            Create Pet Listing
                                        </span>
                                    </span>
                                    <span class="absolute inset-0 bg-gradient-to-r from-green-600 via-emerald-600 to-teal-600"></span>
                                    <span class="absolute inset-0 bg-gradient-to-r from-green-500 via-emerald-500 to-teal-500 opacity-0 hover:opacity-100 transition-opacity duration-300"></span>
                                </Button>
                            </div>
                        </div>
                        
                        <!-- Progress Indicator -->
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ completedSteps.length }} of {{ totalSteps }} steps completed</span>
                                <span class="font-semibold text-primary-600 dark:text-primary-400">
                                    {{ Math.round((completedSteps.length / totalSteps) * 100) }}% Complete
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
/* Animations */
@keyframes float-slow {
    0%, 100% { 
        transform: translate(0, 0) scale(1); 
        opacity: 0.8; 
    }
    50% { 
        transform: translate(10px, 10px) scale(1.05); 
        opacity: 1; 
    }
}

@keyframes float-medium {
    0%, 100% { 
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
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
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
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
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
    box-shadow: 0 0 0 2px white, 0 0 0 4px #6366F1;
}
</style>
