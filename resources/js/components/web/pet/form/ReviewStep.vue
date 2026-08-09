<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Home,
    MapPin,
    Camera,
    Stethoscope,
    Smile,
    Info,
    Check,
} from 'lucide-vue-next';
import { FileText } from 'lucide-vue-next';
import { useTranslations } from '@/composables/useTranslations';

interface Props {
    form: any;
    petTraits: Array<{ id: string; label: string }>;
    categories: Array<{ id: string; name: string }>;
    breeds: Record<string, Array<{ id: string; name: string }>>;
    listingTypes: Array<{ value: number; label: string }>;
}

const props = defineProps<Props>();

const { t } = useTranslations();

const getCategoryName = (id: string) => {
    return (
        props.categories.find((c) => c.id === id)?.name ||
        t('wizard.not_provided')
    );
};

const getBreedName = (categoryId: string, breedId: string) => {
    if (!categoryId || !breedId || !props.breeds[categoryId]) {
        return t('wizard.not_provided');
    }
    return (
        props.breeds[categoryId].find((b) => b.id === breedId)?.name ||
        t('wizard.not_provided')
    );
};

const getListingTypeName = (value: number) => {
    const keys: Record<number, string> = {
        1: 'listing_types.adoption',
        2: 'listing_types.sale',
        3: 'listing_types.mating',
    };
    return t(keys[value] ?? 'listing_types.adoption');
};

const traitLabel = (traitId: string) => {
    const found = props.petTraits.find((trait) => trait.id === traitId);
    return found?.label ?? traitId;
};
</script>

<template>
    <div id="step-8" class="step-container animate-fade-in">
        <Card
            class="group relative overflow-hidden border-2 border-green-100/50 shadow-lg backdrop-blur-md transition-all duration-500 hover:border-green-300 hover:shadow-2xl dark:border-green-900/30 dark:bg-gray-800/70 dark:hover:border-green-700"
        >
            <!-- Animated Background Gradient -->
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-br from-green-50/30 via-emerald-50/20 to-teal-50/10 opacity-0 transition-opacity duration-700 group-hover:opacity-100 dark:from-green-900/20 dark:via-emerald-900/10 dark:to-teal-900/5"
            ></div>
            <!-- Decorative Corner -->
            <div
                class="absolute end-0 top-0 h-32 w-32 rounded-bl-full bg-gradient-to-br from-green-100/20 to-transparent opacity-50 dark:from-green-900/10"
            ></div>
            <CardHeader class="relative z-10">
                <div class="flex items-center space-x-4">
                    <div
                        class="relative rounded-2xl bg-gradient-to-br from-green-500 to-emerald-600 p-3 text-white shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:shadow-xl"
                    >
                        <div
                            class="absolute inset-0 animate-pulse rounded-2xl bg-white/20"
                        ></div>
                        <FileText class="relative z-10 h-6 w-6" />
                    </div>
                    <div>
                        <CardTitle
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                            >{{
                                t('wizard.review_your_pet_listing')
                            }}</CardTitle
                        >
                        <CardDescription
                            class="text-gray-500 dark:text-gray-400"
                            >{{
                                t('wizard.review_before_submitting')
                            }}</CardDescription
                        >
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Basic Information Review -->
                <div
                    class="rounded-xl border border-primary-200 bg-gradient-to-r from-primary-50 to-purple-50 p-4 dark:border-primary-800 dark:from-primary-900/20 dark:to-purple-900/20"
                >
                    <h3
                        class="mb-3 flex items-center text-lg font-semibold text-gray-800 dark:text-white"
                    >
                        <Home
                            class="me-2 h-5 w-5 text-primary-600 dark:text-primary-400"
                        />
                        {{ t('wizard.basic_information') }}
                    </h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.name_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{
                                    form.name || t('wizard.not_provided')
                                }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.type_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{ getCategoryName(form.type) }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.breed_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{ getBreedName(form.type, form.breed) }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.age_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{
                                    form.age || t('wizard.not_provided')
                                }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.gender_label')
                            }}</span>
                            <span
                                class="font-medium capitalize text-gray-800 dark:text-white"
                                >{{
                                    form.gender === 'male'
                                        ? t('wizard.male')
                                        : form.gender === 'female'
                                          ? t('wizard.female')
                                          : form.gender ||
                                            t('wizard.not_provided')
                                }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.color_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{
                                    form.color || t('wizard.not_provided')
                                }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.weight_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{
                                    form.weight || t('wizard.not_provided')
                                }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.listing_type_label')
                            }}</span>
                            <span
                                class="font-medium capitalize text-gray-800 dark:text-white"
                                >{{
                                    getListingTypeName(form.listing_type)
                                }}</span
                            >
                        </div>
                        <div v-if="form.listing_type === 2 && form.price">
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.price_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >${{ form.price }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Location Review -->
                <div
                    class="rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-cyan-50 p-4 dark:border-blue-800 dark:from-blue-900/20 dark:to-cyan-900/20"
                >
                    <h3
                        class="mb-3 flex items-center text-lg font-semibold text-gray-800 dark:text-white"
                    >
                        <MapPin
                            class="me-2 h-5 w-5 text-blue-600 dark:text-blue-400"
                        />
                        {{ t('wizard.location') }}
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div v-if="form.location.address">
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.address_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{ form.location.address }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.city_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{
                                    form.location.city ||
                                    t('wizard.not_provided')
                                }}</span
                            >
                        </div>
                        <div v-if="form.location.state">
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.state_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{ form.location.state }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.country_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{ form.location.country }}</span
                            >
                        </div>
                        <div
                            v-if="
                                form.location.coordinates.lat &&
                                form.location.coordinates.lng
                            "
                        >
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.coordinates')
                            }}</span>
                            <span
                                class="font-mono text-xs font-medium text-gray-800 dark:text-white"
                                >{{ form.location.coordinates.lat.toFixed(6) }},
                                {{
                                    form.location.coordinates.lng.toFixed(6)
                                }}</span
                            >
                        </div>
                        <div
                            v-if="form.location.detailedAddress"
                            class="border-t border-blue-200 pt-2 dark:border-blue-800"
                        >
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.details_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{ form.location.detailedAddress }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Photos Review -->
                <div
                    class="rounded-xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-4 dark:border-amber-800 dark:from-amber-900/20 dark:to-orange-900/20"
                >
                    <h3
                        class="mb-3 flex items-center text-lg font-semibold text-gray-800 dark:text-white"
                    >
                        <Camera
                            class="me-2 h-5 w-5 text-amber-600 dark:text-amber-400"
                        />
                        {{ t('wizard.step_photos') }}
                    </h3>
                    <div class="grid grid-cols-4 gap-3">
                        <div v-if="form.featuredImagePreview" class="relative">
                            <img
                                :src="form.featuredImagePreview"
                                :alt="t('wizard.featured')"
                                class="aspect-square w-full rounded-lg border-2 border-amber-400 object-cover"
                            />
                            <span
                                class="absolute start-1 top-1 rounded bg-amber-500 px-2 py-0.5 text-xs font-semibold text-white"
                                >{{ t('wizard.featured') }}</span
                            >
                        </div>
                        <img
                            v-for="(preview, index) in form.imagePreviews"
                            :key="index"
                            :src="preview"
                            :alt="`Photo ${index + 1}`"
                            class="aspect-square w-full rounded-lg object-cover"
                        />
                        <div
                            v-if="
                                !form.featuredImagePreview &&
                                form.imagePreviews.length === 0
                            "
                            class="col-span-4 py-4 text-center text-gray-500 dark:text-gray-400"
                        >
                            {{ t('wizard.no_photos_uploaded') }}
                        </div>
                    </div>
                </div>

                <!-- Health Review -->
                <div
                    class="rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-50 to-teal-50 p-4 dark:border-emerald-800 dark:from-emerald-900/20 dark:to-teal-900/20"
                >
                    <h3
                        class="mb-3 flex items-center text-lg font-semibold text-gray-800 dark:text-white"
                    >
                        <Stethoscope
                            class="me-2 h-5 w-5 text-emerald-600 dark:text-emerald-400"
                        />
                        {{ t('wizard.health_status') }}
                    </h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.status_label')
                            }}</span>
                            <span
                                class="font-medium capitalize text-gray-800 dark:text-white"
                                >{{ form.health.status }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.vaccinated_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{
                                    form.health.vaccinated
                                        ? t('common.yes')
                                        : t('common.no')
                                }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.spayed_neutered_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{
                                    form.health.spayedNeutered
                                        ? t('common.yes')
                                        : t('common.no')
                                }}</span
                            >
                        </div>
                        <div v-if="form.health.lastVetVisit">
                            <span class="text-gray-500 dark:text-gray-400">{{
                                t('wizard.last_vet_visit_label')
                            }}</span>
                            <span
                                class="font-medium text-gray-800 dark:text-white"
                                >{{ form.health.lastVetVisit }}</span
                            >
                        </div>
                    </div>
                    <div
                        v-if="form.health.specialNeeds"
                        class="mt-3 border-t border-emerald-200 pt-3 dark:border-emerald-800"
                    >
                        <span
                            class="text-sm text-gray-500 dark:text-gray-400"
                            >{{ t('wizard.special_needs_label') }}</span
                        >
                        <p
                            class="mt-1 text-sm font-medium text-gray-800 dark:text-white"
                        >
                            {{ form.health.specialNeeds }}
                        </p>
                    </div>
                </div>

                <!-- Personality Review -->
                <div
                    class="rounded-xl border border-purple-200 bg-gradient-to-r from-purple-50 to-fuchsia-50 p-4 dark:border-purple-800 dark:from-purple-900/20 dark:to-fuchsia-900/20"
                >
                    <h3
                        class="mb-3 flex items-center text-lg font-semibold text-gray-800 dark:text-white"
                    >
                        <Smile
                            class="me-2 h-5 w-5 text-purple-600 dark:text-purple-400"
                        />
                        {{ t('wizard.step_personality') }}
                    </h3>
                    <div v-if="form.description" class="mb-3">
                        <span
                            class="text-sm text-gray-500 dark:text-gray-400"
                            >{{ t('wizard.description_label') }}</span
                        >
                        <p
                            class="mt-1 text-sm font-medium text-gray-800 dark:text-white"
                        >
                            {{ form.description }}
                        </p>
                    </div>
                    <div v-if="form.traits.length > 0">
                        <span
                            class="text-sm text-gray-500 dark:text-gray-400"
                            >{{ t('wizard.traits_label') }}</span
                        >
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="trait in form.traits"
                                :key="trait"
                                class="rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                            >
                                {{ traitLabel(trait) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Healthcare Review -->
                <div
                    v-if="
                        form.health.vaccinations?.length > 0 ||
                        form.health.medications?.length > 0 ||
                        form.health.allergies?.length > 0 ||
                        form.health.vetName
                    "
                    class="rounded-xl border border-rose-200 bg-gradient-to-r from-rose-50 to-pink-50 p-4 dark:border-rose-800 dark:from-rose-900/20 dark:to-pink-900/20"
                >
                    <h3
                        class="mb-3 flex items-center text-lg font-semibold text-gray-800 dark:text-white"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="me-2 h-5 w-5 text-rose-600 dark:text-rose-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
                            />
                        </svg>
                        {{ t('wizard.healthcare_information') }}
                    </h3>

                    <!-- Vaccinations -->
                    <div
                        v-if="form.health.vaccinations?.length > 0"
                        class="mb-3"
                    >
                        <span
                            class="text-sm font-semibold text-gray-500 dark:text-gray-400"
                            >{{ t('wizard.vaccination_records_label') }}</span
                        >
                        <div class="mt-2 space-y-2">
                            <div
                                v-for="(vax, index) in form.health.vaccinations"
                                :key="index"
                                class="flex items-center gap-2 text-sm"
                            >
                                <span
                                    class="rounded bg-rose-100 px-2 py-1 text-xs text-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                                    >{{ vax.date || t('wizard.no_date') }}</span
                                >
                                <span
                                    class="font-medium text-gray-800 dark:text-white"
                                    >{{
                                        vax.name || t('wizard.unknown_vaccine')
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Medications -->
                    <div
                        v-if="form.health.medications?.length > 0"
                        class="mb-3 border-t border-rose-200 pt-3 dark:border-rose-800"
                    >
                        <span
                            class="text-sm font-semibold text-gray-500 dark:text-gray-400"
                            >{{ t('wizard.current_medications_label') }}</span
                        >
                        <div class="mt-2 space-y-2">
                            <div
                                v-for="(med, index) in form.health.medications"
                                :key="index"
                                class="text-sm"
                            >
                                <span
                                    class="font-medium text-gray-800 dark:text-white"
                                    >{{ med.name || 'Unknown' }}</span
                                >
                                <span
                                    v-if="med.usage"
                                    class="text-gray-500 dark:text-gray-400"
                                >
                                    - {{ med.usage }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Allergies -->
                    <div
                        v-if="form.health.allergies?.length > 0"
                        class="mb-3 border-t border-rose-200 pt-3 dark:border-rose-800"
                    >
                        <span
                            class="text-sm font-semibold text-gray-500 dark:text-gray-400"
                            >{{ t('wizard.allergies_label') }}</span
                        >
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="(allergy, index) in form.health
                                    .allergies"
                                :key="index"
                                class="rounded-full bg-rose-100 px-3 py-1 text-xs font-medium text-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                            >
                                {{ allergy || 'Unknown' }}
                            </span>
                        </div>
                    </div>

                    <!-- Veterinarian -->
                    <div
                        v-if="form.health.vetName || form.health.vetPhone"
                        class="border-t border-rose-200 pt-3 dark:border-rose-800"
                    >
                        <span
                            class="text-sm font-semibold text-gray-500 dark:text-gray-400"
                            >{{ t('wizard.veterinarian_label') }}</span
                        >
                        <div class="mt-2 text-sm">
                            <div v-if="form.health.vetName">
                                <span
                                    class="font-medium text-gray-800 dark:text-white"
                                    >{{ form.health.vetName }}</span
                                >
                            </div>
                            <div
                                v-if="form.health.vetPhone"
                                class="text-gray-600 dark:text-gray-300"
                            >
                                {{ form.health.vetPhone }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Info Review -->
                <div
                    v-if="
                        form.additionalInfo.some(
                            (info) => info.key && info.value,
                        )
                    "
                    class="rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-blue-50 p-4 dark:border-indigo-800 dark:from-indigo-900/20 dark:to-blue-900/20"
                >
                    <h3
                        class="mb-3 flex items-center text-lg font-semibold text-gray-800 dark:text-white"
                    >
                        <Info
                            class="me-2 h-5 w-5 text-indigo-600 dark:text-indigo-400"
                        />
                        {{ t('wizard.additional_information') }}
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div
                            v-for="(info, index) in form.additionalInfo"
                            :key="index"
                        >
                            <div v-if="info.key && info.value">
                                <span class="text-gray-500 dark:text-gray-400"
                                    >{{ info.key }}:</span
                                >
                                <span
                                    class="ms-2 font-medium text-gray-800 dark:text-white"
                                    >{{ info.value }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirmation Message -->
                <div
                    class="rounded-xl border-2 border-green-300 bg-gradient-to-r from-green-50 to-emerald-50 p-4 dark:border-green-700 dark:from-green-900/20 dark:to-emerald-900/20"
                >
                    <div class="flex items-start space-x-3">
                        <Check
                            class="mt-0.5 h-6 w-6 flex-shrink-0 text-green-600 dark:text-green-400"
                        />
                        <div>
                            <h4
                                class="mb-1 font-semibold text-gray-800 dark:text-white"
                            >
                                {{ t('wizard.ready_to_submit') }}
                            </h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ t('wizard.ready_to_submit_desc') }}
                            </p>
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
[class*='transition'],
[class*='transform'],
[class*='scale'] {
    will-change: transform;
    transform: translateZ(0);
    -webkit-transform: translateZ(0);
}
</style>
